<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE orders ADD COLUMN print_count INT DEFAULT 0 AFTER status");
    echo "Added print_count.<br>";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "<br>";
}
try {
    $db->exec("ALTER TABLE orders ADD COLUMN kitchen_print_count INT DEFAULT 0 AFTER print_count");
    echo "Added kitchen_print_count.<br>";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "<br>";
}
