<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM settings WHERE setting_key='enable_stock_tracking'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
