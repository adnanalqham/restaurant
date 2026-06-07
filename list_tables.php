<?php
require_once __DIR__ . '/config/db.php';
try {
    $db = getDB();
    echo "<h1>قائمة الجداول في القاعدة: " . DB_NAME . "</h1>";
    echo "<ul>";
    foreach ($tables as $t) { echo "<li>$t</li>"; }
    echo "</ul>";

    echo "<h2>أعمدة جدول users:</h2>";
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll();
    echo "<pre>" . print_r($cols, true) . "</pre>";

    echo "<h2>أعمدة جدول roles:</h2>";
    $cols = $db->query("SHOW COLUMNS FROM roles")->fetchAll();
    echo "<pre>" . print_r($cols, true) . "</pre>";

    echo "<h2>محاولة تشغيل الاستعلام الكامل...</h2>";
    $sql = "SELECT u.id, u.name, u.name_en, u.username, u.is_active, u.can_print, u.created_at,
                   r.name as role, r.name_ar as role_ar, u.role_id
            FROM users u JOIN roles r ON u.role_id = r.id LIMIT 1";
    $stmt = $db->query($sql);
    $row = $stmt->fetch();
    echo "<p>نجح الاستعلام الكامل! </p>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>الخطأ الكامل: " . $e->getMessage() . "</h3>";
}
