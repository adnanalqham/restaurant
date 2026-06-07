<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

$oStmt = $db->query("SELECT id, order_number, status, is_stock_deducted FROM orders ORDER BY id DESC LIMIT 5");
$orders = $oStmt->fetchAll(PDO::FETCH_ASSOC);

$sRow = $db->query("SELECT * FROM item_stock WHERE item_id=1008")->fetch(PDO::FETCH_ASSOC);
$logs = $db->query("SELECT * FROM item_stock_log WHERE item_id=1008 ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

print_r(['orders' => $orders, 'stock_1008' => $sRow, 'logs' => $logs]);
