<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

echo "<h3>تحديث قاعدة البيانات - أوقات التحضير</h3>";

try {
    $db->exec("ALTER TABLE order_items ADD COLUMN prep_start_time DATETIME DEFAULT NULL AFTER status");
    echo "✅ تم إضافة عمود prep_start_time بنجاح.<br>";
} catch (Exception $e) {
    echo "⚠️ ملاحظة: " . $e->getMessage() . "<br>";
}

try {
    $db->exec("ALTER TABLE order_items ADD COLUMN prep_end_time DATETIME DEFAULT NULL AFTER prep_start_time");
    echo "✅ تم إضافة عمود prep_end_time بنجاح.<br>";
} catch (Exception $e) {
    echo "⚠️ ملاحظة: " . $e->getMessage() . "<br>";
}

echo "<br><b style='color:green'>اكتمل التحديث! يمكنك الآن حذف هذا الملف.</b>";
