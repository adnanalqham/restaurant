<?php
/**
 * api/print_helper.php
 * A simple helper to trigger print jobs from anywhere in the PHP system.
 */
require_once __DIR__ . '/../config/db.php';

function triggerPrint($action, $orderId, $printerId = null, $forcePrintAll = false) {
  $currentUser = getCurrentUser();
  $settings = getSettings();
  $serverUrl = $settings['print_server_url'] ?? '';
  
  if (empty($serverUrl)) {
    error_log("Print trigger failed: print_server_url is not configured in settings.");
    return false;
  }

  // Check if auto-print is enabled for this action (skip check if forcePrintAll is true)
  if (!$forcePrintAll) {
    if ($action === 'kitchen' && ($settings['auto_print_kitchen'] ?? '0') !== '1') return true;
    if ($action === 'receipt' && ($settings['auto_print_receipt'] ?? '0') !== '1') return true;
  }

  $serverKey = $settings['print_server_key'] ?? '';
  
  $endpointMap = [
    'receipt' => '/print/receipt',
    'kitchen' => '/print/kitchen',
    'test'    => '/print/test'
  ];

  if (!isset($endpointMap[$action])) return false;

  $url = rtrim($serverUrl, '/') . $endpointMap[$action];
  
  // Prepare payload
  $db = getDB();
  $stmt = $db->prepare("
    SELECT o.order_number, o.total, o.manual_discount, o.refund_amount, 
           o.payment_method, o.created_at, o.table_number, o.notes,
           w.name AS waiter_name, c.name AS cashier_name
    FROM orders o 
    LEFT JOIN users w ON o.waiter_id = w.id
    LEFT JOIN users c ON o.cashier_id = c.id
    WHERE o.id = ?
  ");
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$order) return false;

  // For Kitchen: optionally only print unprinted items
  $itemFilter = "";
  if ($action === 'kitchen' && !$forcePrintAll) {
      $itemFilter = " AND is_printed = 0";
  }

  $iStmt = $db->prepare("
    SELECT id, item_name_ar AS name, quantity AS qty, unit_price AS price, subtotal AS total, notes
    FROM order_items 
    WHERE order_id = ? AND status != 'rejected' $itemFilter 
    ORDER BY id
  ");
  $iStmt->execute([$orderId]);
  $itemsToPrint = $iStmt->fetchAll(PDO::FETCH_ASSOC);

  // If no items to print for kitchen (e.g. all already printed), skip
  if (empty($itemsToPrint) && $action === 'kitchen') return true;

  $order['items'] = $itemsToPrint;

  // ─── Build Unified JSON Schema ──────────────────────────────────────────
  $payload = [
    'orderId' => $orderId,
    'type'    => $action, // kitchen | receipt
    'printerId' => $printerId,
    'isAddition' => ($action === 'kitchen' && !$forcePrintAll), // Flag for printer to show "ADDITION" header
    'meta'    => [
      'number' => $order['order_number'],
      'table'  => $order['table_number'] ?: '-',
      'waiter' => $order['waiter_name'] ?: '-',
      'cashier'=> $order['cashier_name'] ?: '-',
      'time'   => date('Y-m-d H:i:s', strtotime($order['created_at'])),
      'notes'  => $order['notes']
    ],
    'items'   => array_map(function($i) {
        return [
          'name'  => $i['name'],
          'qty'   => (int)$i['qty'],
          'price' => (float)$i['price'],
          'total' => (float)$i['total'],
          'notes' => $i['notes']
        ];
    }, $order['items']),
    'totals'  => [
      'subtotal' => (float)$order['total'] + (float)($order['manual_discount'] ?? 0),
      'discount' => (float)($order['manual_discount'] ?? 0),
      'total'    => (float)$order['total'] - (float)($order['refund_amount'] ?? 0)
    ]
  ];

  $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_TIMEOUT        => (int)($settings['print_timeout_sec'] ?? 5),
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'X-Api-Key: ' . $serverKey
    ]
  ]);

  $res = curl_exec($ch);
  $err = curl_error($ch);
  // curl_close is no longer needed in PHP 8.0+ and is deprecated in 8.5
  
  
  // If success, mark items as printed
  if ($res !== false && !empty($itemsToPrint)) {
      $itemIds = array_column($itemsToPrint, 'id');
      if (!empty($itemIds)) {
          $marks = implode(',', array_fill(0, count($itemIds), '?'));
          $db->prepare("UPDATE order_items SET is_printed = 1 WHERE id IN ($marks)")->execute($itemIds);
      }
  }

  // Log Success/Failure
  $logStmt = $db->prepare("INSERT INTO print_logs (user_id, order_id, printer_type, status, error_message) VALUES (?,?,?,?,?)");
  $logStmt->execute([
    $currentUser ? $currentUser['id'] : 0,
    $orderId,
    'ip',
    $res !== false ? 'success' : 'failed',
    $err ?: ($res === false ? 'Timeout or connection refused' : null)
  ]);

  return $res !== false;
}
