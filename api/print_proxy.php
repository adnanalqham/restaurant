<?php
/**
 * api/print_proxy.php
 * Receives print requests from the PHP system and forwards to Node.js Print Server
 *
 * POST /api/print_proxy.php?action=receipt|kitchen|test
 * Body: { order: {...} }
 */
require_once __DIR__ . '/../config/db.php';
startSession();

// ─── Auth ─────────────────────────────────────────────────────────────────────
$_appToken     = $_GET['_t']                    ?? '';
$_apiKeyHeader = $_SERVER['HTTP_X_API_KEY']     ?? '';
$_userAgent    = $_SERVER['HTTP_USER_AGENT']    ?? '';
$_settings     = getSettings();
$_validKey     = $_settings['kitchen_api_key']  ?? '';

$currentUser = getCurrentUser();
if (!$currentUser) {
    $authOk = ($_appToken === 'SHEBA_APP_2026')
        || ($_apiKeyHeader === 'SHEBA_APP_2026')
        || (!empty($_validKey) && !empty($_apiKeyHeader) && hash_equals($_validKey, $_apiKeyHeader))
        || stripos($_userAgent, 'ShebaApp') !== false
        || stripos($_userAgent, 'okhttp')   !== false;

    if (!$authOk) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'غير مصرح']));
    }
    $currentUser = ['id' => 0, 'role' => 'kitchen', 'name' => 'App'];
}

// ─── Print Server Config ──────────────────────────────────────────────────
define('PRINT_SERVER_URL', 'http://192.168.1.100:3000');   // ← غيّر هذا لـ IP الكمبيوتر
define('PRINT_SERVER_KEY', 'rest-print-2026-secret');       // ← يجب أن يطابق .env
define('PRINT_TIMEOUT',    8);                              // ثواني

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Merge GET order_id into body if present (used by frontend for fetching JSON)
if (empty($body['order_id']) && !empty($_GET['order_id'])) {
    $body['order_id'] = $_GET['order_id'];
}

// Map action to endpoint
$endpointMap = [
    'receipt'         => ['path' => '/print/receipt',      'method' => 'POST'],
    'kitchen'         => ['path' => '/print/kitchen',      'method' => 'POST'],
    'test'            => ['path' => '/print/test',         'method' => 'POST'],
    'printers_get'    => ['path' => '/printers',           'method' => 'GET'],
    'printers_add'    => ['path' => '/printers',           'method' => 'POST'],
    'printers_update' => ['path' => '/printers/{id}',      'method' => 'PUT'],
    'printers_delete' => ['path' => '/printers/{id}',      'method' => 'DELETE'],
    'printers_ping'   => ['path' => '/printers/{id}/ping', 'method' => 'POST'],
    'queue_status'    => ['path' => '/printers/queue/status', 'method' => 'GET'],
    'health'          => ['path' => '/health',             'method' => 'GET'],
];

if (!isset($endpointMap[$action])) {
    jsonResponse(false, 'action غير معروف');
}

$target   = $endpointMap[$action];
$path     = $target['path'];
// Replace {id} if provided in query
if (strpos($path, '{id}') !== false) {
    if (empty($_GET['id'])) jsonResponse(false, 'id مطلوب لهذه العملية');
    $path = str_replace('{id}', $_GET['id'], $path);
}

$endpoint = PRINT_SERVER_URL . $path;
$method   = $target['method'];

// ─── Build Unified JSON Payload ───────────────────────────────────────────
if (!empty($body['order_id'])) {
    $db    = getDB();
    $ordId = (int)$body['order_id'];

    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.total, o.manual_discount, o.refund_amount,
               o.payment_method, o.status, o.created_at, o.table_number, o.notes,
               w.name AS waiter_name, w.printer_mac
        FROM orders o LEFT JOIN users w ON o.waiter_id = w.id
        WHERE o.id = ?
    ");
    $stmt->execute([$ordId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) jsonResponse(false, 'الطلب غير موجود');

    // Fetch items — include category_id so Flutter app can filter by chef's allowed categories
    $iStmt = $db->prepare("
        SELECT item_name_ar AS name, item_name_en AS name_en,
               quantity AS qty, unit_price AS price, subtotal AS total,
               notes, category_id
        FROM order_items WHERE order_id = ? AND status != 'rejected' ORDER BY id
    ");
    $iStmt->execute([$ordId]);
    $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);


    $unifiedJob = [
        'orderId'   => $order['id'],
        'type'      => $action === 'receipt' ? 'receipt' : 'kitchen',
        'printerId' => $body['printerId'] ?? null,
        'printer_mac' => $order['printer_mac'], // Compatibility
        'printerAddress' => $order['printer_mac'], // For the Android Bridge
        'meta'      => [
            'number' => $order['order_number'],
            'table'  => $order['table_number'] ?: '-',
            'waiter' => $order['waiter_name'] ?: '-',
            'time'   => date('Y-m-d H:i:s', strtotime($order['created_at'])),
            'notes'  => $order['notes']
        ],
        'items'     => array_map(function($i) {
            return [
                'name'        => $i['name'],
                'name_en'     => $i['name_en'] ?? '',
                'qty'         => (int)$i['qty'],
                'price'       => (float)$i['price'],
                'total'       => (float)$i['total'],
                'notes'       => $i['notes'],
                'category_id' => (int)($i['category_id'] ?? 0),
            ];
        }, $items),
        'totals'    => [
            'subtotal' => (float)$order['total'] + (float)($order['manual_discount'] ?? 0),
            'discount' => (float)($order['manual_discount'] ?? 0),
            'total'    => (float)$order['total'] - (float)($order['refund_amount'] ?? 0)
        ]
    ];

    // If the caller explicitly wants the JSON (for Bluetooth/Native), return it directly
    if (isset($_GET['json'])) {
        jsonResponse(true, $unifiedJob);
    }

    $body = $unifiedJob;
}

// ─── Forward to Central Print Server (IP Printing) ────────────────────────
$json    = json_encode($body, JSON_UNESCAPED_UNICODE);
$ch      = curl_init($endpoint);

$curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_TIMEOUT        => PRINT_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_POSTFIELDS     => ($method === 'POST' || $method === 'PUT') ? $json : null,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Api-Key: ' . PRINT_SERVER_KEY,
    ],
];

curl_setopt_array($ch, $curlOpts);

$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || !$result) {
    // Return the unified job anyway so the frontend can attempt Bluetooth fallback
    jsonResponse(false, [
        'error' => 'تعذّر الاتصال بخادم الطباعة المركزي',
        'job'   => $body
    ]);
}

echo $result;
