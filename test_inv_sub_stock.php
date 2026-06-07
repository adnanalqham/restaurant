<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE inv_sub_stock;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $db->query("SELECT * FROM inv_sub_stock LIMIT 5;");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
