<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    $db->prepare("UPDATE settings SET setting_value='1' WHERE setting_key='enable_stock_tracking'")->execute();
    echo "Setting updated.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
