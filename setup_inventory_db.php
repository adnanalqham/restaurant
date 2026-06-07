<?php
/**
 * سكربت تهيئة وتحديث نظام المخزن (نسخة الإصلاح)
 * ارفع هذا الملف للاستضافة وشغله لإضافة الأعمدة الناقصة
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();

echo "<h3>جاري تحديث وإصلاح جداول المخزن...</h3>";

try {
    // 1. التأكد من وجود الجداول الأساسية
    $queries = [
        "CREATE TABLE IF NOT EXISTS inv_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_number VARCHAR(50) UNIQUE,
            name VARCHAR(255) NOT NULL,
            unit VARCHAR(50) NOT NULL,
            current_stock DECIMAL(10,2) DEFAULT 0,
            min_stock DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS inv_purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            price DECIMAL(10,2) DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES inv_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS inv_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requester_id INT NOT NULL,
            status ENUM('pending', 'approved', 'issued', 'rejected', 'cancelled') DEFAULT 'pending',
            notes TEXT,
            rejection_reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS inv_request_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            item_id INT NOT NULL,
            requested_qty DECIMAL(10,2) NOT NULL,
            approved_qty DECIMAL(10,2) DEFAULT NULL,
            issued_qty DECIMAL(10,2) DEFAULT NULL,
            FOREIGN KEY (request_id) REFERENCES inv_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES inv_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS item_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            user_id INT NOT NULL,
            action_type ENUM('create', 'update', 'delete') NOT NULL,
            field_name VARCHAR(50),
            old_value TEXT,
            new_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($queries as $q) { $db->exec($q); }

    // 2. إضافة الأعمدة الناقصة (إصلاح الخطأ 1054)
    echo "<h4>جاري فحص الأعمدة الناقصة...</h4>";
    
    // إضافة created_by لجدول المشتريات
    try {
        $db->exec("ALTER TABLE inv_purchases ADD COLUMN created_by INT AFTER notes");
        echo "✅ تمت إضافة عمود created_by بنجاح.<br>";
    } catch(Exception $e) {}

    // إضافة coordinator_id و warehouse_manager_id لجدول الطلبات
    try {
        $db->exec("ALTER TABLE inv_requests ADD COLUMN coordinator_id INT AFTER status");
        $db->exec("ALTER TABLE inv_requests ADD COLUMN warehouse_manager_id INT AFTER coordinator_id");
        echo "✅ تمت إضافة أعمدة المسؤولين بنجاح.<br>";
    } catch(Exception $e) {}

    echo "<p style='color:green; font-weight:bold'>✅ تمت عملية الإصلاح بنجاح! جرب النظام الآن.</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>❌ خطأ: " . $e->getMessage() . "</p>";
}
