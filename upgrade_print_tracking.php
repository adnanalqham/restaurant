<?php
// HARDCODED CONNECTION FOR CLI UPGRADE
$host = 'localhost';
$user = 'root';
$pass = '771603365';
$name = 'restaurant_pos';

echo "<h2>تحديث قاعدة البيانات: تتبع الطباعة للمطبخ</h2>\n";

try {
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // 1. Add is_printed column to order_items
    echo "Processing 'order_items' table...\n";
    try {
        $db->exec("ALTER TABLE order_items ADD COLUMN is_printed TINYINT(1) DEFAULT 0 AFTER status");
        echo "✅ تم إضافة عمود 'is_printed' لتتبع الأصناف المطبوعة.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ عمود 'is_printed' موجود مسبقاً.\n";
        } else {
            echo "⚠️ خطأ في إضافة العمود: " . $e->getMessage() . "\n";
        }
    }

    // 2. Mark existing items as printed so they don't reprint
    $sql = "UPDATE order_items SET is_printed = 1 WHERE is_printed = 0";
    $count = $db->exec($sql);
    echo "✅ تم تمييز $count صنف كـ 'مطبوع' (الأصناف القديمة).\n";

    echo "\nDatabase update complete!\n";

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage() . "\n";
}
