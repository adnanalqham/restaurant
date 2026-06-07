<?php
/**
 * Database Update Script
 * Run this by accessing it via browser once uploaded to your server.
 * Example: https://yourdomain.com/update_db.php
 * After running, please DELETE this file for security.
 */

require_once __DIR__ . '/config/db.php';

// Simple security: check for a secret key if you want, or just rely on manual deletion.
// if (($_GET['key'] ?? '') !== 'some_secret') die('Unauthorized');

$db = getDB();

echo "<h2>إبدا تحديث قاعدة البيانات...</h2>";
echo "<pre>";

try {
    // 1. Add can_print column to users table
    $stmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'can_print'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `can_print` TINYINT(1) DEFAULT 1 AFTER `is_active`");
        echo "✅ تم إضافة عمود can_print لجدول users بنجاح.\n";
    } else {
        echo "ℹ️ عمود can_print موجود مسبقاً في جدول users.\n";
    }

    echo "\n🎉 اكتمل التحديث بنجاح!";
} catch (Exception $e) {
    echo "❌ خطأ أثناء التحديث: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p style='color:red; font-weight:bold;'>يرجى حذف هذا الملف (update_db.php) من الخادم فوراً لدواعي أمنية.</p>";
