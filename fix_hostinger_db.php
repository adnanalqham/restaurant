<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

echo "<div style='font-family: Arial, sans-serif; text-align: right; direction: rtl; padding: 20px;'>";
echo "<h2>تحديث وإصلاح قاعدة البيانات 🛠️</h2>";

function addColumnIfNotExists($db, $table, $column, $definition) {
    try {
        $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "<p style='color: green;'>✅ تم إضافة العمود <b>$column</b> لجدول $table بنجاح.</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ العمود <b>$column</b> موجود مسبقاً في جدول $table.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ خطأ في $column: " . $e->getMessage() . "</p>";
    }
}

// Fix missing size_name column that causes 500 error during order creation
addColumnIfNotExists($db, 'order_items', 'size_name', 'VARCHAR(255) DEFAULT NULL AFTER item_id');

// Ensure prep time columns exist (just in case)
addColumnIfNotExists($db, 'order_items', 'prep_start_time', 'DATETIME DEFAULT NULL AFTER status');
addColumnIfNotExists($db, 'order_items', 'prep_end_time', 'DATETIME DEFAULT NULL AFTER prep_start_time');

// Ensure other potentially missing columns from recent updates
addColumnIfNotExists($db, 'orders', 'service_charge', 'DECIMAL(10,2) DEFAULT 0.00 AFTER tax');

echo "<hr>";
echo "<h3 style='color: green;'>🎉 اكتمل التحديث! يمكنك الآن تجربة إنشاء طلب. يرجى حذف هذا الملف بعد الانتهاء.</h3>";
echo "</div>";
