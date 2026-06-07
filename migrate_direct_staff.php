<?php
/**
 * migrate_direct_staff.php
 * تشغيل مرة واحدة فقط لإنشاء جدول المباشرين وإضافة الحقل للطلبات
 */
require_once __DIR__ . '/config/db.php';

$db = getDB();
$results = [];

// 1. Create direct_staff table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `direct_staff` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `name`       VARCHAR(100) NOT NULL,
            `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
            `sort_order` INT         NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $results[] = ['status' => 'ok', 'msg' => '✅ جدول direct_staff تم إنشاؤه (أو موجود مسبقاً)'];
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'msg' => '❌ خطأ في إنشاء الجدول: ' . $e->getMessage()];
}

// 2. Add direct_name column to orders (safe: won't fail if exists)
try {
    $cols = $db->query("SHOW COLUMNS FROM `orders` LIKE 'direct_name'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `direct_name` VARCHAR(100) DEFAULT NULL AFTER `notes`");
        $results[] = ['status' => 'ok', 'msg' => '✅ تم إضافة عمود direct_name إلى جدول orders'];
    } else {
        $results[] = ['status' => 'info', 'msg' => 'ℹ️ العمود direct_name موجود مسبقاً في orders'];
    }
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'msg' => '❌ خطأ في إضافة العمود: ' . $e->getMessage()];
}

// 3. Add index for quick lookup
try {
    $idx = $db->query("SHOW INDEX FROM `orders` WHERE Key_name='idx_direct_name'")->fetch();
    if (!$idx) {
        $db->exec("CREATE INDEX idx_direct_name ON orders(direct_name)");
        $results[] = ['status' => 'ok', 'msg' => '✅ تم إنشاء الفهرس idx_direct_name'];
    } else {
        $results[] = ['status' => 'info', 'msg' => 'ℹ️ الفهرس موجود مسبقاً'];
    }
} catch (Exception $e) {
    $results[] = ['status' => 'error', 'msg' => '⚠️ لم يمكن إنشاء الفهرس (غير حرج): ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>Migration - المباشرين</title>
<style>
body { font-family: sans-serif; max-width: 700px; margin: 40px auto; direction: rtl; }
.ok { color: green; } .error { color: red; } .info { color: #666; }
li { margin: 8px 0; font-size: 1.1rem; }
</style>
</head>
<body>
<h2>🔄 Migration: جدول المباشرين</h2>
<ul>
<?php foreach ($results as $r): ?>
<li class="<?= $r['status'] ?>"><?= htmlspecialchars($r['msg']) ?></li>
<?php endforeach; ?>
</ul>
<?php $hasError = in_array('error', array_column($results, 'status')); ?>
<?php if (!$hasError): ?>
<p style="color:green;font-weight:bold;font-size:1.2rem">✅ تم بنجاح! يمكنك حذف هذا الملف الآن.</p>
<?php else: ?>
<p style="color:red;font-weight:bold">❌ يوجد أخطاء — تحقق من الرسائل أعلاه.</p>
<?php endif; ?>
<p><a href="admin/direct_staff.php">← الذهاب لإدارة المباشرين</a></p>
</body>
</html>
