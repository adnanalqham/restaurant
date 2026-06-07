<?php
/**
 * Database Upgrade Script: Professional Discount System
 * ---------------------------------------------------
 * This script adds columns for service charges and manual discounts
 * to the orders table. Run this once on your server.
 */

// Include your DB configuration
require_once __DIR__ . '/config/db.php';

try {
    $db = getDB();
    
    echo "Starting Database Update...<br>";

    // 1. Add service_charge column
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN service_charge DECIMAL(10,2) DEFAULT 0.00 AFTER tax");
        echo "✅ Added 'service_charge' column.<br>";
    } catch (Exception $e) { echo "⚠️ service_charge already exists or logic skipped.<br>"; }

    // 2. Add manual_discount column
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN manual_discount DECIMAL(10,2) DEFAULT 0.00 AFTER service_charge");
        echo "✅ Added 'manual_discount' column.<br>";
    } catch (Exception $e) { echo "⚠️ manual_discount already exists.<br>"; }

    // 3. Add discount_reason column
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN discount_reason TEXT AFTER manual_discount");
        echo "✅ Added 'discount_reason' column.<br>";
    } catch (Exception $e) { echo "⚠️ discount_reason already exists.<br>"; }

    // 4. Migrate existing data (Optional but recommended)
    try {
        // Move old service charge values from the old 'discount' column to the new 'service_charge' column
        $db->exec("UPDATE orders SET service_charge = discount WHERE service_charge = 0 AND discount > 0");
        echo "✅ Migrated existing automated fees to the new structure.<br>";
    } catch (Exception $e) { echo "⚠️ Migration error or already done.<br>"; }

    // 5. Add custom permissions column to users
    try {
        $db->exec("ALTER TABLE users ADD COLUMN permissions TEXT NULL AFTER role");
        echo "✅ Added 'permissions' column to users table.<br>";
    } catch (Exception $e) { echo "⚠️ permissions column already exists.<br>"; }

    echo "<br><b>Database update complete! You can now delete this file from your server.</b>";

} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
