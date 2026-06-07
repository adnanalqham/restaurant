<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

// Check current value
$stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `key`='enable_stock_tracking'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Current value: ";
print_r($row);

// Insert or update the setting
if ($row) {
    $db->prepare("UPDATE settings SET `value`='1' WHERE `key`='enable_stock_tracking'")->execute();
    echo "<br>Updated to 1.";
} else {
    $db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('enable_stock_tracking', '1')")->execute();
    echo "<br>Inserted with value 1.";
}

// Verify
$stmt2 = $db->query("SELECT `key`, `value` FROM settings WHERE `key`='enable_stock_tracking'");
print_r($stmt2->fetch(PDO::FETCH_ASSOC));
