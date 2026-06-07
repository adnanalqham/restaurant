<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE orders ADD COLUMN is_stock_deducted TINYINT(1) DEFAULT 0");
    echo "Column added.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
