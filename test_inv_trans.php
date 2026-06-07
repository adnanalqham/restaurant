<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE inventory_transactions;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $db->query("SELECT * FROM inventory_transactions LIMIT 5;");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
