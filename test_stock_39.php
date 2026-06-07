<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

$sRow = $db->query("SELECT * FROM item_stock WHERE item_id=39")->fetch(PDO::FETCH_ASSOC);
$logs = $db->query("SELECT * FROM item_stock_log WHERE item_id=39 ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

print_r(['stock_39' => $sRow, 'logs' => $logs]);
