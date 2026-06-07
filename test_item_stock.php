<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE item_stock;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $db->query("SELECT * FROM items WHERE name_ar LIKE '%أجنحة%' LIMIT 1");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
