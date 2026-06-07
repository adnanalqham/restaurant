<?php
/**
 * api/print_direct_lib.php — ESC/POS shared library
 *
 * Contains only reusable functions (no auth, no session, no output).
 * Included by both:
 *   - api/print_direct.php  (handles HTTP requests from the browser)
 *   - api/orders.php        (auto-prints kitchen ticket on order confirm)
 */

// ── Lookup printer names from DB ──────────────────────────────────────────────
// Reads the windows_name from the `printers` table (managed by admin in admin/printers.php).
// $fallback is used ONLY when the DB table is empty or has no row for $type.
// Set $fallback='' to silently skip printing if no printer is configured.
if (!function_exists('getPrinterName')) {
    function getPrinterName(PDO $db, string $type, string $fallback = ''): string {
        try {
            $s = $db->prepare("SELECT windows_name FROM printers WHERE type = ? AND windows_name != '' ORDER BY id LIMIT 1");
            $s->execute([$type]);
            $r = $s->fetchColumn();
            return $r ?: $fallback;
        } catch (Exception $e) {
            return $fallback;
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// ESC/POS BUILDER — CUSTOMER RECEIPT
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('buildReceiptESC')) {
    function buildReceiptESC(string $restName, array $o, array $items,
                             float $discount, float $subtotal, float $netTotal): string
    {
        $INIT     = "\x1B\x40";
        $LF       = "\x0A";
        $CENTER   = "\x1B\x61\x01";
        $LEFT     = "\x1B\x61\x00";
        $BOLD_ON  = "\x1B\x45\x01";
        $BOLD_OFF = "\x1B\x45\x00";
        $BIG_ON   = "\x1B\x21\x10";
        $BIG_OFF  = "\x1B\x21\x00";
        $CUT      = "\x1D\x56\x00";   // Full cut
        $W        = 32;

        $line = function(string $label, string $value) use ($W): string {
            $pad = max(1, $W - strlen($label) - strlen($value));
            return $label . str_repeat(' ', $pad) . $value;
        };

        $b = $INIT;

        // Header
        $b .= $CENTER . $BOLD_ON . $BIG_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF;
        $b .= str_pad('SALES RECEIPT', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        // Order info
        $b .= $CENTER . $BIG_ON . $BOLD_ON;
        $table = 'TABLE: ' . ($o['table_number'] ?: 'Takeaway');
        $b .= str_pad($table, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF . $BOLD_OFF . $LEFT;
        $b .= str_repeat('-', $W) . $LF;

        $b .= $line('Order No.', '#' . $o['order_number']) . $LF;
        if (!empty($o['waiter_name'])) {
            $w = trim(preg_replace('/[^\x20-\x7E]/', '', $o['waiter_name']));
            if ($w) $b .= $line('Waiter   ', $w) . $LF;
        }
        if (!empty($o['cashier_name'])) {
            $c = trim(preg_replace('/[^\x20-\x7E]/', '', $o['cashier_name']));
            if ($c) $b .= $line('Cashier  ', $c) . $LF;
        }
        if (!empty($o['direct_name'])) {
            $d = trim(preg_replace('/[^\x20-\x7E]/', '', $o['direct_name']));
            if ($d) $b .= $line('Staff    ', $d) . $LF;
        }
        $b .= $line('Date     ', date('Y-m-d H:i', strtotime($o['created_at']))) . $LF;
        if (!empty($o['notes'])) {
            $n = trim(preg_replace('/[^\x20-\x7E]/', '', $o['notes']));
            if ($n) $b .= $line('Note     ', substr($n, 0, 14)) . $LF;
        }
        $b .= str_repeat('-', $W) . $LF;

        // Items
        foreach ($items as $item) {
            $name = trim($item['name_en'] ?? '');
            if ($name === '') $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['name'] ?? ''));
            if ($name === '') $name = 'Item';
            if (strlen($name) > $W) $name = substr($name, 0, $W - 1) . '.';

            $total = number_format((float)$item['total'], 2);
            $qty   = (int)$item['qty'];
            $price = number_format((float)$item['price'], 2);

            $pad = max(1, $W - strlen($name) - strlen($total));
            $b .= $name . str_repeat(' ', $pad) . $total . $LF;

            $detail = 'x' . $qty . ' @ ' . $price;
            $b .= str_repeat(' ', max(0, $W - strlen($detail))) . $detail . $LF;

            if (!empty($item['notes'])) {
                $nt = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
                if ($nt) $b .= '  * ' . substr($nt, 0, $W - 5) . $LF;
            }
        }
        $b .= str_repeat('-', $W) . $LF;

        // Totals
        if ($discount > 0) {
            $b .= $line('Subtotal', number_format($subtotal, 2)) . $LF;
            $b .= $line('Discount', '-' . number_format($discount, 2)) . $LF;
            $b .= str_repeat('-', $W) . $LF;
        }
        if (!empty($o['payment_method'])) {
            $pm = trim(strtoupper(preg_replace('/[^\x20-\x7E]/', '', $o['payment_method'])));
            if ($pm) $b .= $line('Payment ', $pm) . $LF;
        }
        $b .= str_repeat('=', $W) . $LF;
        $b .= $BOLD_ON . $line('TOTAL', number_format($netTotal, 2) . ' YER') . $LF . $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        // Footer
        $b .= $CENTER;
        $b .= str_pad('Thank you for visiting!', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $LF . $LF . $LF;
        $b .= $CUT;  // ✂ Cut after customer receipt

        // ── Waiter / Kitchen copy ──────────────────────────────────────────────
        $b .= $CENTER . $BOLD_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= str_pad('KITCHEN / WAITER COPY', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        $b .= $LEFT;
        $b .= $CENTER . $BIG_ON . $BOLD_ON;
        $table = 'TABLE: ' . ($o['table_number'] ?: 'Takeaway');
        $b .= str_pad($table, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF . $BOLD_OFF . $LEFT;
        $b .= str_repeat('-', $W) . $LF;

        $b .= $line('Order No.', '#' . $o['order_number']) . $LF;
        if (!empty($o['waiter_name'])) {
            $w = trim(preg_replace('/[^\x20-\x7E]/', '', $o['waiter_name']));
            if ($w) $b .= $line('Waiter   ', $w) . $LF;
        }
        if (!empty($o['cashier_name'])) {
            $c = trim(preg_replace('/[^\x20-\x7E]/', '', $o['cashier_name']));
            if ($c) $b .= $line('Cashier  ', $c) . $LF;
        }
        $b .= $line('Time     ', date('H:i', strtotime($o['created_at']))) . $LF;
        $b .= str_repeat('=', $W) . $LF;

        foreach ($items as $item) {
            $name = trim($item['name_en'] ?? '');
            if ($name === '') $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['name'] ?? ''));
            if ($name === '') $name = 'Item';
            $qty = (int)$item['qty'];

            $qStr = 'x' . $qty;
            $pad  = max(1, $W - strlen($qStr) - strlen($name));
            $b .= $BOLD_ON . $qStr . str_repeat(' ', $pad) . $name . $LF . $BOLD_OFF;

            if (!empty($item['notes'])) {
                $nt = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
                if ($nt) $b .= '  ** ' . substr($nt, 0, $W - 7) . ' **' . $LF;
            }
            $b .= str_repeat('-', $W) . $LF;
        }

        $b .= str_repeat('=', $W) . $LF;
        $b .= $CENTER . str_pad('** KEEP FOR REFERENCE **', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $LF . $LF . $LF;
        $b .= $CUT;  // ✂ Cut after waiter copy

        return $b;
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// ESC/POS BUILDER — KITCHEN TICKET
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('buildKitchenESC')) {
    function buildKitchenESC(string $restName, array $o, array $items): string
    {
        $INIT     = "\x1B\x40";
        $LF       = "\x0A";
        $CENTER   = "\x1B\x61\x01";
        $LEFT     = "\x1B\x61\x00";
        $BOLD_ON  = "\x1B\x45\x01";
        $BOLD_OFF = "\x1B\x45\x00";
        $BIG_ON   = "\x1B\x21\x30"; // Double height + width (biggest available)
        $BIG_OFF  = "\x1B\x21\x00";
        $CUT      = "\x1D\x56\x00"; // Full cut
        $W        = 32;

        $b = $INIT;

        // Header
        $b .= $CENTER . $BOLD_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        // Table + Order + Time — BIG
        $b .= $CENTER . $BIG_ON . $BOLD_ON;
        $table = 'TABLE: ' . ($o['table_number'] ?: 'TKW');
        $b .= str_pad($table, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF . $BOLD_OFF;

        $b .= $LEFT;
        $pad = max(1, $W - strlen('Order: #' . $o['order_number']) - strlen('Time: ' . date('H:i', strtotime($o['created_at']))));
        $b .= 'Order: #' . $o['order_number'] . str_repeat(' ', $pad) . 'Time: ' . date('H:i', strtotime($o['created_at'])) . $LF;

        // Cashier & Staff names
        if (!empty($o['cashier_name'])) {
            $cn = trim(preg_replace('/[^\x20-\x7E]/', '', $o['cashier_name']));
            if ($cn) $b .= 'Cashier: ' . $cn . $LF;
        }
        if (!empty($o['direct_name'])) {
            $dn = trim(preg_replace('/[^\x20-\x7E]/', '', $o['direct_name']));
            if ($dn) $b .= 'Staff: ' . $dn . $LF;
        } elseif (!empty($o['waiter_name'])) {
            $wn = trim(preg_replace('/[^\x20-\x7E]/', '', $o['waiter_name']));
            if ($wn) $b .= 'Waiter: ' . $wn . $LF;
        }

        $b .= str_repeat('=', $W) . $LF;

        // Items — large and clear
        foreach ($items as $item) {
            $name = trim($item['name_en'] ?? '');
            if ($name === '') $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['name'] ?? ''));
            if ($name === '') $name = 'Item';
            $qty = (int)$item['qty'];

            // Quantity large
            $b .= $BIG_ON . $BOLD_ON;
            $qStr = 'x' . $qty;
            $b .= $qStr . $LF;
            $b .= $BIG_OFF . $BOLD_ON;

            // Item name bold
            if (strlen($name) > $W) $name = substr($name, 0, $W - 1) . '.';
            $b .= $name . $LF . $BOLD_OFF;

            if (!empty($item['notes'])) {
                $nt = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
                if ($nt) $b .= '  !! ' . substr($nt, 0, $W - 6) . $LF;
            }
            $b .= str_repeat('-', $W) . $LF;
        }

        // Footer
        $b .= str_repeat('=', $W) . $LF;
        if (!empty($o['notes'])) {
            $n = trim(preg_replace('/[^\x20-\x7E]/', '', $o['notes']));
            if ($n) $b .= 'NOTE: ' . substr($n, 0, $W - 6) . $LF;
        }
        $b .= $CENTER . '** HANDLE WITH CARE **' . $LF;
        $b .= $LF . $LF . $LF;
        $b .= $CUT; // ✂ Cut

        return $b;
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// RAW PRINT via PowerShell P/Invoke (winspool.drv)
// Sends ESC/POS bytes directly — bypasses GDI, no dialog
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('rawPrint')) {
    function rawPrint(string $printerName, string $data): array {
        if (!$printerName) return ['ok' => false, 'msg' => 'اسم الطابعة غير محدد'];

        if (!function_exists('shell_exec') ||
            in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            return ['ok' => false, 'msg' => 'shell_exec معطّل (يعمل فقط على الجهاز المحلي)'];
        }

        $base    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pos_' . time() . '_' . rand(100, 999);
        $binFile = $base . '.bin';
        $psFile  = $base . '.ps1';

        file_put_contents($binFile, $data);

        $ps = <<<'PS'
param([string]$PrinterName, [string]$BinFile)
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class RawPrint {
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Auto)]
    public class DOCINFO {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName  = "POS";
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile = null;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType = "RAW";
    }
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool OpenPrinter(string n, out IntPtr h, IntPtr d);
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool ClosePrinter(IntPtr h);
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool StartDocPrinter(IntPtr h,int l,DOCINFO d);
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.drv",SetLastError=true)] public static extern bool WritePrinter(IntPtr h,byte[] b,int c,out int w);
    public static bool Send(string printer, byte[] bytes) {
        IntPtr hPrinter;
        if (!OpenPrinter(printer, out hPrinter, IntPtr.Zero)) return false;
        StartDocPrinter(hPrinter,1,new DOCINFO());
        StartPagePrinter(hPrinter);
        int written=0;
        WritePrinter(hPrinter, bytes, bytes.Length, out written);
        EndPagePrinter(hPrinter);
        EndDocPrinter(hPrinter);
        ClosePrinter(hPrinter);
        return written > 0;
    }
}
"@
$bytes = [System.IO.File]::ReadAllBytes($BinFile)
if ([RawPrint]::Send($PrinterName, $bytes)) { Write-Output "OK" } else { Write-Output "FAIL" }
PS;

        file_put_contents($psFile, $ps);

        $printerEsc = str_replace('"', '`"', $printerName);
        $cmd = 'powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass'
             . ' -File "' . $psFile . '"'
             . ' -PrinterName "' . $printerEsc . '"'
             . ' -BinFile "' . $binFile . '" 2>&1';
        $out = trim((string)shell_exec($cmd));

        @unlink($binFile);
        @unlink($psFile);

        if ($out === 'OK')   return ['ok' => true,  'msg' => '✅ تمت الطباعة'];
        if ($out === 'FAIL') return ['ok' => false, 'msg' => '❌ فشل — تحقق من اسم الطابعة: "' . $printerName . '"'];
        return               ['ok' => false, 'msg' => 'خطأ: ' . substr($out, 0, 200)];
    }
}
