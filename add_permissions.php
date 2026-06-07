<?php
require_once __DIR__ . '/config/db.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE users ADD COLUMN permissions TEXT NULL");
    echo "SUCCESS";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "SUCCESS (Already exists)";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
