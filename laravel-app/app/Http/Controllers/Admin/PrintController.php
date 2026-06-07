<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PrintController — ESC/POS raw bytes via PowerShell P/Invoke
 *
 * action=receipt → print customer receipt to cashier printer
 * action=kitchen → print kitchen ticket to kitchen printer
 * action=all     → print BOTH simultaneously (one click, no dialogs)
 * action=test    → print test page to cashier printer
 *
 * Printer names are read from the `printers` table (windows_name field).
 * Fallback: settings['usb_printer_name']
 *
 * IMPORTANT: This only works on the local machine where PHP is installed.
 * shell_exec() must be enabled.
 */
class PrintController extends Controller
{
    // ── Lookup printer by type ─────────────────────────────────────────────
    private function getPrinterName(string $type, string $fallback = ''): string
    {
        try {
            $name = DB::table('printers')
                ->where('type', $type)
                ->whereNotNull('windows_name')
                ->where('windows_name', '!=', '')
                ->orderBy('id')
                ->value('windows_name');
            return $name ?: $fallback;
        } catch (\Exception $e) {
            return $fallback;
        }
    }

    // ── Get setting value ──────────────────────────────────────────────────
    private function getSetting(string $key, string $default = ''): string
    {
        try {
            $val = DB::table('settings')->where('key', $key)->value('value');
            return $val ?? $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    // ── Test print ─────────────────────────────────────────────────────────
    public function test(Request $request)
    {
        $restName  = $this->getSetting('restaurant_name', 'Restaurant');
        $cashierPrinter = $this->getPrinterName('cashier',
            $this->getSetting('usb_printer_name', 'chashier'));

        $testOrder = [
            'order_number' => 'TEST', 'table_number' => '5',
            'waiter_name'  => 'Test', 'created_at' => now()->toDateTimeString(),
            'manual_discount' => 0, 'total' => 100, 'refund_amount' => 0,
            'payment_method' => 'cash', 'notes' => '',
        ];
        $testItems = [['name' => 'Test Item', 'name_en' => 'Test Item',
                        'qty' => 1, 'price' => 100, 'total' => 100, 'notes' => '']];

        $bytes  = $this->buildReceiptESC($restName, $testOrder, $testItems, 0, 100, 100);
        $result = $this->rawPrint($cashierPrinter, $bytes);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['msg'],
            'printer' => $cashierPrinter,
        ]);
    }

    // ── Print receipt (cashier) ────────────────────────────────────────────
    public function receipt(Request $request, int $orderId)
    {
        $data = $this->fetchOrderData($orderId);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);
        }

        $cashierPrinter = $this->getPrinterName('cashier',
            $this->getSetting('usb_printer_name', 'chashier'));

        [$order, $items, $discount, $subtotal, $netTotal] = $data;
        $restName = $this->getSetting('restaurant_name', 'Restaurant');

        $bytes  = $this->buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal);
        $result = $this->rawPrint($cashierPrinter, $bytes);

        if ($result['ok']) {
            try {
                DB::table('orders')->where('id', $orderId)
                    ->increment('print_count');
            } catch (\Exception $e) {}
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['msg'],
            'printer' => $cashierPrinter,
        ]);
    }

    // ── Print kitchen ticket ───────────────────────────────────────────────
    public function kitchen(Request $request, int $orderId)
    {
        $data = $this->fetchOrderData($orderId);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);
        }

        $kitchenPrinter = $this->getPrinterName('kitchen', 'MNK on 10.0.0.191');

        [$order, $items] = $data;
        $restName = $this->getSetting('restaurant_name', 'Restaurant');

        $bytes  = $this->buildKitchenESC($restName, $order, $items);
        $result = $this->rawPrint($kitchenPrinter, $bytes);

        if ($result['ok']) {
            try {
                DB::table('orders')->where('id', $orderId)
                    ->increment('kitchen_print_count');
            } catch (\Exception $e) {}
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['msg'],
            'printer' => $kitchenPrinter,
        ]);
    }

    // ── Print BOTH simultaneously ──────────────────────────────────────────
    public function all(Request $request, int $orderId)
    {
        $data = $this->fetchOrderData($orderId);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);
        }

        $restName       = $this->getSetting('restaurant_name', 'Restaurant');
        $cashierPrinter = $this->getPrinterName('cashier',
            $this->getSetting('usb_printer_name', 'chashier'));
        $kitchenPrinter = $this->getPrinterName('kitchen', 'MNK on 10.0.0.191');

        [$order, $items, $discount, $subtotal, $netTotal] = $data;

        $receiptBytes = $this->buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal);
        $kitchenBytes = $this->buildKitchenESC($restName, $order, $items);

        $r1 = $this->rawPrint($cashierPrinter, $receiptBytes);
        $r2 = $this->rawPrint($kitchenPrinter, $kitchenBytes);

        try {
            if ($r1['ok']) DB::table('orders')->where('id', $orderId)->increment('print_count');
            if ($r2['ok']) DB::table('orders')->where('id', $orderId)->increment('kitchen_print_count');
        } catch (\Exception $e) {}

        $ok  = $r1['ok'] || $r2['ok'];
        $msg = '🧾 ' . ($r1['ok'] ? '✅' : '❌') . ' الكاشير | 🍳 ' . ($r2['ok'] ? '✅' : '❌') . ' المطبخ';

        return response()->json([
            'success' => $ok,
            'message' => $msg,
            'cashier' => ['printer' => $cashierPrinter, 'ok' => $r1['ok'], 'msg' => $r1['msg']],
            'kitchen' => ['printer' => $kitchenPrinter, 'ok' => $r2['ok'], 'msg' => $r2['msg']],
        ]);
    }

    // ── Fetch order + items from DB ────────────────────────────────────────
    private function fetchOrderData(int $orderId): ?array
    {
        $order = DB::table('orders as o')
            ->leftJoin('users as w', 'o.waiter_id', '=', 'w.id')
            ->select('o.id', 'o.order_number', 'o.total', 'o.manual_discount',
                     'o.refund_amount', 'o.payment_method', 'o.created_at',
                     'o.table_number', 'o.notes', 'w.name as waiter_name')
            ->where('o.id', $orderId)
            ->first();

        if (!$order) return null;

        $order = (array)$order;

        $items = DB::table('order_items')
            ->select('item_name_ar as name', 'item_name_en as name_en',
                     'quantity as qty', 'unit_price as price',
                     'subtotal as total', 'notes')
            ->where('order_id', $orderId)
            ->where('status', '!=', 'rejected')
            ->orderBy('id')
            ->get()
            ->map(fn($i) => (array)$i)
            ->toArray();

        $discount  = (float)($order['manual_discount'] ?? 0);
        $subtotal  = (float)$order['total'] + $discount;
        $netTotal  = (float)$order['total'] - (float)($order['refund_amount'] ?? 0);

        return [$order, $items, $discount, $subtotal, $netTotal];
    }

    // ══════════════════════════════════════════════════════════════════
    // ESC/POS BUILDER — CUSTOMER RECEIPT (فاتورة الزبون + نسخة الويتر)
    // ══════════════════════════════════════════════════════════════════
    private function buildReceiptESC(string $restName, array $o, array $items,
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
        $CUT      = "\x1D\x56\x00";  // Full cut ✂
        $W        = 32;

        $line = function(string $label, string $value) use ($W): string {
            $pad = max(1, $W - strlen($label) - strlen($value));
            return $label . str_repeat(' ', $pad) . $value;
        };

        $b = $INIT;

        // ─── Header ───────────────────────────────────────────────────
        $b .= $CENTER . $BOLD_ON . $BIG_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF;
        $b .= str_pad('SALES RECEIPT', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        // ─── Order info ───────────────────────────────────────────────
        $b .= $LEFT;
        $b .= $line('Order No.', '#' . $o['order_number']) . $LF;
        $b .= $line('Table    ', $o['table_number'] ?: 'Takeaway') . $LF;
        if (!empty($o['waiter_name'])) {
            $w = trim(preg_replace('/[^\x20-\x7E]/', '', $o['waiter_name']));
            if ($w) $b .= $line('Waiter   ', $w) . $LF;
        }
        $b .= $line('Date     ', date('Y-m-d H:i', strtotime($o['created_at']))) . $LF;
        if (!empty($o['notes'])) {
            $n = trim(preg_replace('/[^\x20-\x7E]/', '', $o['notes']));
            if ($n) $b .= $line('Note     ', substr($n, 0, 14)) . $LF;
        }
        $b .= str_repeat('-', $W) . $LF;

        // ─── Items ────────────────────────────────────────────────────
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

        // ─── Totals ───────────────────────────────────────────────────
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

        // ─── Footer ───────────────────────────────────────────────────
        $b .= $CENTER;
        $b .= str_pad('Thank you for visiting!', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $LF . $LF . $LF;
        $b .= $CUT;  // ✂ Cut after customer receipt

        // ─── Waiter / Kitchen copy ─────────────────────────────────────
        $b .= $CENTER . $BOLD_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= str_pad('KITCHEN / WAITER COPY', $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        $b .= $LEFT;
        $b .= $line('Order No.', '#' . $o['order_number']) . $LF;
        $b .= $line('Table    ', $o['table_number'] ?: 'Takeaway') . $LF;
        if (!empty($o['waiter_name'])) {
            $w = trim(preg_replace('/[^\x20-\x7E]/', '', $o['waiter_name']));
            if ($w) $b .= $line('Waiter   ', $w) . $LF;
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

    // ══════════════════════════════════════════════════════════════════
    // ESC/POS BUILDER — KITCHEN TICKET (تذكرة المطبخ بأحرف كبيرة)
    // ══════════════════════════════════════════════════════════════════
    private function buildKitchenESC(string $restName, array $o, array $items): string
    {
        $INIT     = "\x1B\x40";
        $LF       = "\x0A";
        $CENTER   = "\x1B\x61\x01";
        $LEFT     = "\x1B\x61\x00";
        $BOLD_ON  = "\x1B\x45\x01";
        $BOLD_OFF = "\x1B\x45\x00";
        $BIG_ON   = "\x1B\x21\x30"; // Double height + width (biggest available)
        $BIG_OFF  = "\x1B\x21\x00";
        $CUT      = "\x1D\x56\x00"; // Full cut ✂
        $W        = 32;

        $b = $INIT;

        // ─── Header ───────────────────────────────────────────────────
        $b .= $CENTER . $BOLD_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;
        $b .= str_repeat('=', $W) . $LF;

        // ─── Table + Order + Time — BIG ───────────────────────────────
        $b .= $CENTER . $BIG_ON . $BOLD_ON;
        $table = 'TABLE: ' . ($o['table_number'] ?: 'TKW');
        $b .= str_pad($table, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF . $BOLD_OFF;

        $b .= $LEFT;
        $orderLine = 'Order: #' . $o['order_number'];
        $timeLine  = 'Time: ' . date('H:i', strtotime($o['created_at']));
        $pad = max(1, $W - strlen($orderLine) - strlen($timeLine));
        $b .= $orderLine . str_repeat(' ', $pad) . $timeLine . $LF;
        $b .= str_repeat('=', $W) . $LF;

        // ─── Items — large and clear ───────────────────────────────────
        foreach ($items as $item) {
            $name = trim($item['name_en'] ?? '');
            if ($name === '') $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['name'] ?? ''));
            if ($name === '') $name = 'Item';
            $qty = (int)$item['qty'];

            // Quantity large
            $b .= $BIG_ON . $BOLD_ON;
            $b .= 'x' . $qty . $LF;
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

        // ─── Footer ───────────────────────────────────────────────────
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

    // ══════════════════════════════════════════════════════════════════
    // RAW PRINT via PowerShell P/Invoke (winspool.drv)
    // Sends ESC/POS bytes directly — bypasses GDI, no dialog
    // ══════════════════════════════════════════════════════════════════
    private function rawPrint(string $printerName, string $data): array
    {
        if (!$printerName) {
            return ['ok' => false, 'msg' => 'اسم الطابعة غير محدد'];
        }

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
