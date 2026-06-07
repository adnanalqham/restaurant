<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

try {
    echo "Starting Database Fix...<br>";

    // Add name_en to users table
    echo "Adding 'name_en' column to 'users' table... ";
    $db->exec("ALTER TABLE `users` ADD COLUMN `name_en` VARCHAR(150) NULL AFTER `name`");
    echo "Done.<br>";

    echo "<br><b>Success! The database has been updated with the English Name column. You can now delete this file.</b>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<br><b style='color:green'>Success! The column 'name_en' already exists. You can now delete this file.</b>";
    } else {
        echo "<br><b style='color:red'>Error: " . $e->getMessage() . "</b>";
    }
}

