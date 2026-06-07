<?php
/**
 * upgrade_stock_system.php
 * رفع هذا الملف للاستضافة وافتحه مرة واحدة لإنشاء جداول نظام رصيد الأصناف
 */
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/config/db.php';

function addColumnSafe(PDO $db, string $sql): string {
    try {
        $db->query($sql);
        return '✅ نجح';
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
            strpos($e->getMessage(), 'already exists') !== false) {
            return '⚠️ موجود مسبقاً';
        }
        throw $e;
    }
}

$steps = [];
try {
    $db = getDB();

    // 1. Create item_stock table
    $db->exec("CREATE TABLE IF NOT EXISTS `item_stock` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `item_id`    INT NOT NULL,
        `stock_qty`  DECIMAL(10,2) NOT NULL DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` INT NULL,
        UNIQUE KEY `uk_item_stock` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $steps[] = ['label' => 'جدول item_stock', 'result' => '✅ تم الإنشاء / موجود'];

    // 2. Create item_stock_log table
    $db->exec("CREATE TABLE IF NOT EXISTS `item_stock_log` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `item_id`      INT NOT NULL,
        `item_name_ar` VARCHAR(255) NULL,
        `action_type`  VARCHAR(30) NOT NULL,
        `qty_before`   DECIMAL(10,2) NULL,
        `qty_change`   DECIMAL(10,2) NULL,
        `qty_after`    DECIMAL(10,2) NULL,
        `note`         VARCHAR(500) NULL,
        `user_id`      INT NULL,
        `user_name`    VARCHAR(255) NULL,
        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_isl_item` (`item_id`),
        KEY `idx_isl_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $steps[] = ['label' => 'جدول item_stock_log', 'result' => '✅ تم الإنشاء / موجود'];

    // 3. Add enable_stock_tracking setting
    $db->exec("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('enable_stock_tracking', '0')");
    $steps[] = ['label' => 'إعداد enable_stock_tracking', 'result' => '✅ تم'];

    $success = true;
} catch (PDOException $e) {
    $success = false;
    $errorMsg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ترقية نظام رصيد الأصناف</title>
<style>
  body{font-family:Arial,sans-serif;max-width:600px;margin:40px auto;padding:20px}
  .step{padding:12px;margin:8px 0;border-radius:6px;border:1px solid #ddd;display:flex;justify-content:space-between}
  .ok{background:#f0fdf4}.err{background:#fef2f2}
  h2{color:#1d6f42}h2.fail{color:#c0392b}
</style>
</head>
<body>
<?php if ($success): ?>
<h2>✅ تم التحديث بنجاح!</h2>
<?php foreach ($steps as $s): ?>
<div class="step ok"><span><?= $s['label'] ?></span><strong><?= $s['result'] ?></strong></div>
<?php endforeach; ?>
<p style="color:#e74c3c;font-weight:700;margin-top:20px">⚠️ يرجى حذف هذا الملف من الاستضافة الآن لأسباب أمنية.</p>
<?php else: ?>
<h2 class="fail">❌ حدث خطأ</h2>
<div class="step err"><span><?= htmlspecialchars($errorMsg) ?></span></div>
<?php endif; ?>
</body>
</html>
