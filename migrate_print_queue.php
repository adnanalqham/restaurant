<?php
/**
 * Print Queue Migration
 * Access once: http://yourdomain.com/restaurant/migrate_print_queue.php
 * Delete after running.
 */
require_once __DIR__ . '/config/db.php';
$db = getDB();
echo "<h2>تحديث قاعدة البيانات - طوابير الطباعة</h2><pre>";

try {
    // Create print_queue table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `print_queue` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `order_id`    INT NOT NULL,
            `station_user_id` INT NOT NULL,
            `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `printed_at`  TIMESTAMP NULL DEFAULT NULL,
            INDEX `idx_station_pending` (`station_user_id`, `printed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✅ تم إنشاء جدول print_queue بنجاح.\n";
    echo "\n🎉 اكتمل التحديث!";
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage();
}

echo "</pre><p style='color:red;font-weight:bold'>يرجى حذف هذا الملف فوراً بعد التشغيل!</p>";
