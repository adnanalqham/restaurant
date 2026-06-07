<?php
/**
 * Database Upgrade v3: Printers Management
 * Adds dedicated printers table and links categories to printers.
 */
require_once __DIR__ . '/config/db.php';

echo "<h2>ترقية قاعدة البيانات (v3) - إدارة الطابعات المتعددة</h2><hr>";

try {
    $db = getDB();

    // 1. Create printers table
    echo "إيقاف التحقق من القيود...<br>";
    $db->exec("SET FOREIGN_KEY_CHECKS=0;");

    echo "إنشاء جدول الطابعات...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS printers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        ip VARCHAR(50) NOT NULL,
        port INT DEFAULT 9100,
        type ENUM('NETWORK', 'BLUETOOTH') DEFAULT 'NETWORK',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Add printer_id to categories if not exists
    echo "تحديث جدول الفئات...<br>";
    $cols = $db->query("SHOW COLUMNS FROM categories LIKE 'printer_id'")->fetch();
    if (!$cols) {
        $db->exec("ALTER TABLE categories ADD COLUMN printer_id INT DEFAULT NULL AFTER sort_order;");
        $db->exec("ALTER TABLE categories ADD CONSTRAINT fk_category_printer FOREIGN KEY (printer_id) REFERENCES printers(id) ON DELETE SET NULL;");
    }

    // 3. Migrate existing kitchen_printer_ip to printers table
    $settings = getSettings();
    $existingIp = $settings['kitchen_printer_ip'] ?? '';
    
    $check = $db->query("SELECT id FROM printers WHERE name='طابعة المطبخ (افتراضية)' OR ip='$existingIp'")->fetch();
    if (!$check && !empty($existingIp)) {
        echo "نقل إعدادات طابعة المطبخ الحالية...<br>";
        $stmt = $db->prepare("INSERT INTO printers (name, ip, type) VALUES (?, ?, 'NETWORK')");
        $stmt->execute(['طابعة المطبخ (افتراضية)', $existingIp]);
        $printerId = $db->lastInsertId();
        
        // Link all existing categories to this default printer
        $db->exec("UPDATE categories SET printer_id = $printerId WHERE printer_id IS NULL");
    }

    $db->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "<h3 style='color:green'>Done! تم تحديث قاعدة البيانات بنجاح.</h3>";
    echo "<p><a href='admin/categories.php'>الذهاب إلى الفئات</a></p>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
