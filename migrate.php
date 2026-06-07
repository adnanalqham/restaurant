<?php
require_once __DIR__ . '/config/db.php';

$db = getDB();

try {
    // Add type column to inv_warehouses if missing
    $db->query("SELECT type FROM inv_warehouses LIMIT 1");
    echo "inv_warehouses.type exists.<br>";
} catch (PDOException $e) {
    try {
        $db->exec("ALTER TABLE inv_warehouses ADD COLUMN type ENUM('main', 'sub') DEFAULT 'sub'");
        $db->exec("UPDATE inv_warehouses SET type = 'main' WHERE id = 'main'");
        echo "Added type to inv_warehouses.<br>";
    } catch (PDOException $e2) {
        echo "Error adding type: " . $e2->getMessage() . "<br>";
    }
}

try {
    // Add warehouse_id to users if missing
    $db->query("SELECT warehouse_id FROM users LIMIT 1");
    echo "users.warehouse_id exists.<br>";
} catch (PDOException $e) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN warehouse_id VARCHAR(50) DEFAULT NULL");
        
        // Data Migration: Link existing users based on their roles
        $sql = "UPDATE users u 
                JOIN roles r ON u.role_id = r.id 
                SET u.warehouse_id = CASE 
                    WHEN r.name = 'chef' THEN 'kitchen'
                    WHEN r.name = 'juice_bar' THEN 'bar'
                    WHEN r.name = 'waiter_juice' THEN 'shisha'
                    WHEN r.name = 'waiter' THEN 'hall'
                    ELSE NULL 
                END
                WHERE u.warehouse_id IS NULL";
        $db->exec($sql);
        echo "Added warehouse_id to users.<br>";
    } catch (PDOException $e2) {
        echo "Error adding warehouse_id: " . $e2->getMessage() . "<br>";
    }
}
