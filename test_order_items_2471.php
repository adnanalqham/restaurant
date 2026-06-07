<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM order_items WHERE order_id=2471;"); // 2471 is id of 20262429
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
