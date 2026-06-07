<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(); // Base auth

$action = $_GET['action'] ?? '';

try {
    // Schema Migration
    $db = getDB();
    try {
        // Create table if not exists first
        $db->exec("CREATE TABLE IF NOT EXISTS inv_warehouses (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Add type column to inv_warehouses if missing
        $db->query("SELECT type FROM inv_warehouses LIMIT 1");
    } catch (PDOException $e) {
        try {
            $db->exec("ALTER TABLE inv_warehouses ADD COLUMN type ENUM('main', 'sub') DEFAULT 'sub'");
            $db->exec("UPDATE inv_warehouses SET type = 'main' WHERE id = 'main'");
        } catch (PDOException $e2) {}
    }

    try {
        // Add warehouse_id to users if missing
        $db->query("SELECT warehouse_id FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN warehouse_id VARCHAR(50) DEFAULT NULL");
            
            // Data Migration: Link existing users based on their roles
            $sql = "UPDATE users u 
                    JOIN roles r ON u.role_id = r.id 
                    SET u.warehouse_id = CASE 
                        WHEN r.name = 'chef' THEN 'kitchen'
                        WHEN r.name = 'juice_bar' THEN 'bar'
                        WHEN r.name = 'waiter_juice' THEN 'shisha'
                        WHEN r.name = 'waiter' THEN 'hall'
                        ELSE NULL 
                    END
                    WHERE u.warehouse_id IS NULL";
            $db->exec($sql);
        } catch (PDOException $e2) {}
    }

    switch ($action) {
        case 'get_all':
            getAllWarehouses();
            break;
        case 'save':
            saveWarehouse();
            break;
        case 'delete':
            deleteWarehouse();
            break;
        default:
            jsonResponse(false, null, 'Invalid action', 400);
    }
} catch (Throwable $e) {
    jsonResponse(false, null, 'Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(), 500);
}

function getAllWarehouses() {
    $db = getDB();
    $warehouses = $db->query("SELECT * FROM inv_warehouses ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $warehouses);
}

function saveWarehouse() {
    $db = getDB();
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'sub';

    if (empty($name)) jsonResponse(false, null, 'اسم المخزن مطلوب');
    
    // Generate an ID if new
    if (empty($id)) {
        $id = 'wh_' . time();
        $stmt = $db->prepare("INSERT INTO inv_warehouses (id, name, type) VALUES (?, ?, ?)");
        $stmt->execute([$id, $name, $type]);
        jsonResponse(true, null, 'تم إضافة المخزن بنجاح');
    } else {
        $stmt = $db->prepare("UPDATE inv_warehouses SET name = ?, type = ? WHERE id = ?");
        $stmt->execute([$name, $type, $id]);
        jsonResponse(true, null, 'تم تحديث المخزن بنجاح');
    }
}

function deleteWarehouse() {
    $db = getDB();
    $id = $_POST['id'] ?? '';
    if (empty($id)) jsonResponse(false, null, 'ID مطلوب', 400);
    if ($id === 'main') jsonResponse(false, null, 'لا يمكن حذف المخزن الرئيسي', 403);
    
    // Check if linked to users
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE warehouse_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, null, 'لا يمكن الحذف، المخزن مرتبط بمستخدمين حالياً');
    }

    $db->prepare("DELETE FROM inv_warehouses WHERE id = ?")->execute([$id]);
    jsonResponse(true, null, 'تم الحذف بنجاح');
}
