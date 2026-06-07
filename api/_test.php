<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$cols = $db->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(array_column($cols, 'Field'));
