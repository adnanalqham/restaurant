<?php
/**
 * upgrade_db_v2.php
 * Enhances database for the Gold Standard Hybrid Printing System.
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();

try {
    // Note: DDL statements like ALTER/CREATE cause implicit commits in MySQL, so we don't use beginTransaction.
    // 1. Add printer_mac to users table (Support for older MySQL versions without IF NOT EXISTS)
    $cols = $db->query("SHOW COLUMNS FROM users LIKE 'printer_mac'")->fetch();
    if (!$cols) {
        $db->exec("ALTER TABLE users ADD COLUMN printer_mac VARCHAR(50) DEFAULT NULL");
    }

    // 2. Create print_logs table for tracking all print jobs (IP and Bluetooth)
    $db->exec("
        CREATE TABLE IF NOT EXISTS print_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_id INT NOT NULL,
            printer_type ENUM('ip', 'bluetooth') NOT NULL,
            status ENUM('success', 'failed', 'retrying') NOT NULL,
            attempts INT DEFAULT 1,
            error_message TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (order_id),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Ensure Unified Print Settings exist in settings table
    $defaults = [
        'print_server_url'      => 'http://localhost:3000',
        'print_server_key'      => 'rest-print-2026-secret',
        'auto_print_kitchen'    => '1',
        'auto_print_receipt'    => '1',
        'print_fallback_enabled'=> '1',
        'print_retry_limit'     => '3',
        'print_timeout_sec'     => '5'
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    echo "✅ Database upgraded successfully to Printing v2 Standard.";
} catch (Exception $e) {
    echo "❌ Database upgrade failed: " . $e->getMessage();
}

// Clean up script
// unlink(__FILE__);
