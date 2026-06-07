<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$tables = $db->query("SHOW TABLES LIKE 'inv_%'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
