<?php
/**
 * Database Upgrade Script: Offers and Discounts System
 * ---------------------------------------------------
 * This script creates the tables needed for the new offers (combos)
 * and discounts (item/category level) system.
 */

require_once __DIR__ . '/config/db.php';

try {
    $db = getDB();
    
    echo "Starting Database Update for Offers System...<br>";

    // 1. Create offers table (for combos/packages)
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS offers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name_ar VARCHAR(150) NOT NULL,
                name_en VARCHAR(150),
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                image VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                start_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "✅ Created 'offers' table.<br>";
    } catch (Exception $e) { echo "⚠️ Error creating offers table: " . $e->getMessage() . "<br>"; }

    // 2. Create offer_items table (items inside a combo)
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS offer_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                offer_id INT NOT NULL,
                item_id INT NOT NULL,
                quantity INT DEFAULT 1,
                FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "✅ Created 'offer_items' table.<br>";
    } catch (Exception $e) { echo "⚠️ Error creating offer_items table: " . $e->getMessage() . "<br>"; }

    // 3. Create discounts table (for item/category discounts)
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS discounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('item', 'category') NOT NULL,
                target_id INT NOT NULL,
                discount_type ENUM('percent', 'fixed') NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                label VARCHAR(100),
                is_active TINYINT(1) DEFAULT 1,
                start_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "✅ Created 'discounts' table.<br>";
    } catch (Exception $e) { echo "⚠️ Error creating discounts table: " . $e->getMessage() . "<br>"; }

    // 4. Ensure users table has missing columns (permissions, printer_mac)
    try {
        $db->exec("ALTER TABLE users ADD COLUMN permissions LONGTEXT DEFAULT NULL");
        echo "✅ Added 'permissions' column to 'users' table.<br>";
    } catch (Exception $e) { echo "ℹ️ 'permissions' column skip (might already exist).<br>"; }

    try {
        $db->exec("ALTER TABLE users ADD COLUMN printer_mac VARCHAR(50) DEFAULT NULL");
        echo "✅ Added 'printer_mac' column to 'users' table.<br>";
    } catch (Exception $e) { echo "ℹ️ 'printer_mac' column skip (might already exist).<br>"; }

    // 4. Create wallets table (missing from this system)
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS wallets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                account_number VARCHAR(100) NOT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "✅ Created 'wallets' table.<br>";
    } catch (Exception $e) { echo "⚠️ Error creating wallets table: " . $e->getMessage() . "<br>"; }
    
    // 4. Create activity_log table (system monitoring)
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "✅ Created 'activity_log' table.<br>";
    } catch (Exception $e) { echo "⚠️ Error creating activity_log table: " . $e->getMessage() . "<br>"; }

    echo "<br><b>Database update complete! The Offers and Discounts system is ready.</b>";
    echo "<br><a href='admin/index.php'>Return to Dashboard</a>";

} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
