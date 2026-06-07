<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin']);

$user = getCurrentUser();
$action = $_GET['action'] ?? '';

if ($action === 'reset_system') {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $pwd = $input['password'] ?? '';

    // Verify Password
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $uData = $stmt->fetch();
    
    if (!$uData || !password_verify($pwd, $uData['password'])) {
        jsonResponse(false, null, 'كلمة المرور غير صحيحة، لم يتم إجراء أي تغيير.');
    }

    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $db->exec("TRUNCATE TABLE order_items");
        $db->exec("TRUNCATE TABLE orders");
        // Removed TRUNCATE TABLE activity_log to preserve system history
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
    logActivity("تصفير النظام", "بواسطة " . $user['name']);
        
        jsonResponse(true, null, 'تم تصفير النظام وحذف العمليات بنجاح ✅ (تم الحفاظ على مراقبة النظام)');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ أثناء تصفير النظام: ' . $e->getMessage());
    }
}

if ($action === 'update_settings') {
    $db    = getDB();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Allowed setting keys (whitelist for security)
    $allowed = [
        'restaurant_name', 'logo', 'currency', 'tax_rate', 'service_charge_rate',
        'print_server_url', 'print_server_key', 'print_timeout_sec',
        'auto_print_kitchen', 'auto_print_receipt',
        'kitchen_api_key',
        'enable_stock_tracking',
    ];

    $saved = 0;
    $stmt  = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");

    foreach ($input as $key => $value) {
        if (!in_array($key, $allowed)) continue;
        $stmt->execute([$key, $value]);
        $saved++;
    }

    if ($saved === 0) {
        jsonResponse(false, null, 'لم يتم التعرف على أي إعداد صالح للحفظ');
    }

    jsonResponse(true, null, 'تم حفظ الإعدادات بنجاح (' . $saved . ' إعداد)');
}

if ($action === 'backup_db') {
    $db = getDB();
    $dbName = 'restaurant'; // Should ideally be fetched if dynamic
    $filename = "backup_" . date("Y-m-d_H-i-s") . ".sql";
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    try {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        echo "-- Backup for POS Restaurant\n";
        echo "-- Created at: " . date("Y-m-d H:i:s") . "\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            // Get Create Table
            $createRes = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            echo "\n-- Structure for table `$table` --\n";
            echo "DROP TABLE IF EXISTS `$table`;\n";
            echo $createRes['Create Table'] . ";\n\n";

            // Get Data
            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                echo "-- Data for table `$table` --\n";
                foreach ($rows as $row) {
                    $cols = array_keys($row);
                    $vals = array_map(function($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote($v);
                    }, array_values($row));
                    
                    echo "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
                }
            }
            echo "\n";
        }
        
        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        exit;
        
    } catch (Exception $e) {
        die("-- Error generating backup: " . $e->getMessage());
    }
}

jsonResponse(false, null, 'إجراء غير معروف');
