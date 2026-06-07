<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE inv_request_items ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
    $db->exec("ALTER TABLE inv_request_items ADD COLUMN rejection_reason TEXT DEFAULT NULL");
    echo "Columns added successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
