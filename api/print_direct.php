<?php
/**
 * api/print_direct.php — ESC/POS raw bytes via PowerShell P/Invoke
 *
 * action=receipt → print customer receipt to cashier printer
 * action=kitchen → print kitchen ticket to kitchen printer
 * action=all     → print BOTH simultaneously (one click, no dialogs)
 * action=test    → print test page to cashier printer
 *
 * Printer names are read from the `printers` table (windows_name field).
 * Fallback: settings['usb_printer_name']
 *
 * ESC/POS builders and rawPrint() live in api/print_direct_lib.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/print_direct_lib.php';
// ─── Auth ─────────────────────────────────────────────────────────────────────
// Mobile app or CLI print worker sends: ?_t=SHEBA_APP_2026  OR  X-API-Key header  OR session cookie
$_appToken     = $_GET['_t']                    ?? '';
$_apiKeyHeader = $_SERVER['HTTP_X_API_KEY']     ?? '';
$_userAgent    = $_SERVER['HTTP_USER_AGENT']    ?? '';
$_settings     = getSettings();
$_validKey     = $_settings['kitchen_api_key']  ?? '';

$useApiKey = false;

// Check 1: URL token (most reliable)
if ($_appToken === 'SHEBA_APP_2026') {
    $useApiKey = true;
}

// Check 2: X-API-Key header
if (!$useApiKey) {
    if ($_apiKeyHeader === 'SHEBA_APP_2026' ||
        (!empty($_validKey) && !empty($_apiKeyHeader) && hash_equals($_validKey, $_apiKeyHeader))) {
        $useApiKey = true;
    }
}

// Check 3: User-Agent fallback
if (!$useApiKey) {
    if (stripos($_userAgent, 'ShebaApp') !== false ||
        stripos($_userAgent, 'okhttp')   !== false ||
        stripos($_userAgent, 'Dart')     !== false) {
        $useApiKey = true;
    }
}

if (!$useApiKey) {
    startSession();
    requireAuth(['admin', 'cashier', 'waiter', 'inventory_monitor', 'chef', 'juice_bar', 'kitchen']);
    $currentUser = getCurrentUser();
    session_write_close();
} else {
    $currentUser = ['id' => 0, 'role' => 'kitchen', 'name' => 'API Client'];
}

$db       = getDB();
$settings = getSettings();
$action   = $_GET['action'] ?? 'all';
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId  = (int)($input['order_id'] ?? $_GET['order_id'] ?? 0);
$restName = trim(preg_replace('/[^\x20-\x7E]/', '', $settings['restaurant_name'] ?? '')) ?: 'Restaurant';

// Kitchen printer — reads from `printers` table (type='kitchen'), managed by admin at admin/printers.php
// Fallback '' means: skip if not configured (no hardcoded values)
$cashierPrinter = getPrinterName($db, 'cashier', trim($settings['usb_printer_name'] ?? ''));
$kitchenPrinter = getPrinterName($db, 'kitchen');

// ── Test mode ─────────────────────────────────────────────────────────────────
if ($action === 'test') {
    $testOrder = [
        'order_number' => 'TEST', 'table_number' => '5',
        'waiter_name'  => 'Test', 'created_at' => date('Y-m-d H:i:s'),
        'manual_discount' => 0, 'total' => 100, 'refund_amount' => 0,
        'payment_method' => 'cash', 'notes' => '',
        'cashier_name' => '', 'direct_name' => '',
    ];
    $testItems = [['name' => 'Test Item', 'name_en' => 'Test Item', 'qty' => 1, 'price' => 100, 'total' => 100, 'notes' => '']];
    $b = buildReceiptESC($restName, $testOrder, $testItems, 0, 100, 100);
    $r = rawPrint($cashierPrinter, $b);
    jsonResponse($r['ok'], ['printer' => $cashierPrinter], $r['msg']);
}

// ── Validate ──────────────────────────────────────────────────────────────────
if (!$orderId) jsonResponse(false, null, 'order_id مطلوب', 400);

// ── Fetch order ───────────────────────────────────────────────────────────────
$oStmt = $db->prepare("
    SELECT o.id, o.order_number, o.total, o.manual_discount, o.refund_amount,
           o.payment_method, o.created_at, o.table_number, o.notes, o.direct_name,
           w.name AS waiter_name, c.name AS cashier_name
    FROM orders o
    LEFT JOIN users w ON o.waiter_id = w.id
    LEFT JOIN users c ON o.cashier_id = c.id
    WHERE o.id = ?
");
$oStmt->execute([$orderId]);
$order = $oStmt->fetch(PDO::FETCH_ASSOC);
if (!$order) jsonResponse(false, null, 'الطلب غير موجود', 404);

$order['waiter_name'] = trim(
    $currentUser['name_en'] ?? preg_replace('/[^\x20-\x7E]/', '', $currentUser['name'] ?? '')
) ?: ($order['waiter_name'] ?? '');

// ── Fetch items ───────────────────────────────────────────────────────────────
$iStmt = $db->prepare("
    SELECT item_name_ar AS name, item_name_en AS name_en,
           quantity AS qty, unit_price AS price,
           subtotal AS total, notes
    FROM order_items
    WHERE order_id = ? AND status != 'rejected'
    ORDER BY id ASC
");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Totals ────────────────────────────────────────────────────────────────────
$discount = (float)($order['manual_discount'] ?? 0);
$subtotal = (float)$order['total'] + $discount;
$netTotal = (float)$order['total'] - (float)($order['refund_amount'] ?? 0);

// ── Dispatch ──────────────────────────────────────────────────────────────────
switch ($action) {

    case 'receipt':
        $bytes  = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal);
        $result = rawPrint($cashierPrinter, $bytes);
        try {
            if ($result['ok']) $db->prepare("UPDATE orders SET print_count = print_count + 1 WHERE id = ?")->execute([$orderId]);
        } catch (Exception $e) {}
        jsonResponse($result['ok'], ['printer' => $cashierPrinter], $result['msg']);
        break;

    case 'kitchen':
        $bytes  = buildKitchenESC($restName, $order, $items);
        $result = rawPrint($kitchenPrinter, $bytes);
        try {
            if ($result['ok']) $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")->execute([$orderId]);
        } catch (Exception $e) {}
        jsonResponse($result['ok'], ['printer' => $kitchenPrinter], $result['msg']);
        break;

    case 'get_esc':
        $stationUserId = (int)($_GET['station_user_id'] ?? 0);
        $type = 'kitchen'; // default
        if ($stationUserId > 0) {
            try {
                $sStmt = $db->prepare("
                    SELECT r.name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?
                ");
                $sStmt->execute([$stationUserId]);
                $role = $sStmt->fetchColumn();
                if ($role === 'juice_bar') {
                    $type = 'bar';
                }
            } catch (Exception $e) {}
        } else {
            $type = $_GET['type'] ?? 'kitchen';
        }

        $printerName = getPrinterName($db, $type);
        if (empty($printerName)) {
            $printerName = ($type === 'cashier' || $type === 'receipt') 
                ? trim($settings['usb_printer_name'] ?? '') 
                : 'MNK on 10.0.0.191';
        }

        if ($type === 'receipt' || $type === 'cashier') {
            $bytes = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal);
        } else {
            $bytes = buildKitchenESC($restName, $order, $items);
        }

        jsonResponse(true, [
            'printer_name' => $printerName,
            'esc_pos_base64' => base64_encode($bytes)
        ]);
        break;

    case 'all':

    default:
        // Print to BOTH printers — one silent job each
        $receiptBytes = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal);
        $kitchenBytes = buildKitchenESC($restName, $order, $items);

        $r1 = rawPrint($cashierPrinter, $receiptBytes);
        $r2 = rawPrint($kitchenPrinter, $kitchenBytes);

        try {
            if ($r1['ok']) $db->prepare("UPDATE orders SET print_count = print_count + 1 WHERE id = ?")->execute([$orderId]);
            if ($r2['ok']) $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")->execute([$orderId]);
        } catch (Exception $e) {}

        $ok  = $r1['ok'] || $r2['ok'];
        $msg = '🧾 ' . ($r1['ok'] ? '✅' : '❌') . ' الكاشير | 🍳 ' . ($r2['ok'] ? '✅' : '❌') . ' المطبخ';
        jsonResponse($ok, [
            'cashier' => ['printer' => $cashierPrinter, 'ok' => $r1['ok'], 'msg' => $r1['msg']],
            'kitchen' => ['printer' => $kitchenPrinter, 'ok' => $r2['ok'], 'msg' => $r2['msg']],
        ], $msg);
        break;
}
