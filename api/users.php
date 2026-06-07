<?php
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Allow waiters to fetch cashiers without being an admin
if ($method === 'GET' && isset($_GET['role'])) {
    requireAuth(); // basic auth for logged in users
} else {
    requireAuth(['admin']); // strictly admin for CRUD operations on users
}

switch ($method) {
    case 'GET':
        $action === 'permissions' ? getUserPermissions() : getUsers();
        break;
    case 'POST':
        switch ($action) {
            case 'update':      updateUser();      break;
            case 'reset_pass':  resetPassword();   break;
            case 'permissions': savePermissions(); break;
            case 'delete':      deleteUser();      break;
            default:            createUser();       break;
        }
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getUsers() {
    $db = getDB();

    // --- AUTO MIGRATION ---
    try {
        $db->query("SELECT warehouse_id FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN warehouse_id VARCHAR(50) DEFAULT NULL");
            $sqlMigrate = "UPDATE users u JOIN roles r ON u.role_id = r.id 
                    SET u.warehouse_id = CASE 
                        WHEN r.name = 'chef' THEN 'kitchen'
                        WHEN r.name = 'juice_bar' THEN 'bar'
                        WHEN r.name = 'waiter_juice' THEN 'shisha'
                        WHEN r.name = 'waiter' THEN 'hall'
                        ELSE NULL 
                    END WHERE u.warehouse_id IS NULL";
            $db->exec($sqlMigrate);
        } catch (PDOException $e2) {}
    }
    // --- AUTO MIGRATION: print_type ---
    try {
        $db->query("SELECT print_type FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN print_type ENUM('network','bluetooth','chef') NOT NULL DEFAULT 'network'");
        } catch (PDOException $e2) {}
    }
    // Update existing enum if needed (add 'chef' value)
    try {
        $db->exec("ALTER TABLE users MODIFY COLUMN print_type ENUM('network','bluetooth','chef') NOT NULL DEFAULT 'network'");
    } catch (PDOException $e) {}
    // -----------------------

    $roleFilter = $_GET['role'] ?? '';
    $activeFilter = isset($_GET['active']) ? (int)$_GET['active'] : null;

    $sql = "SELECT u.id, u.name, u.name_en, u.username, u.is_active, u.can_print, u.print_type, u.created_at, u.permissions, u.printer_mac, u.warehouse_id,
                   r.name as role, r.name_ar as role_ar, u.role_id
            FROM users u JOIN roles r ON u.role_id = r.id
            WHERE 1=1";
    $params = [];

    if ($roleFilter) {
        $sql .= " AND r.name = ?";
        $params[] = $roleFilter;
    }
    if ($activeFilter !== null) {
        $sql .= " AND u.is_active = ?";
        $params[] = $activeFilter;
    }

    $sql .= " ORDER BY r.id, u.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Attach permissions (only if not a simple dropdown request)
    if (!$roleFilter) {
        foreach ($users as &$u) {
            $pStmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id=?");
            $pStmt->execute([$u['id']]);
            $u['category_ids'] = array_column($pStmt->fetchAll(), 'category_id');
        }
        $roles = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();
        jsonResponse(true, ['users' => $users, 'roles' => $roles]);
    } else {
        // Return flat list for dropdowns
        jsonResponse(true, $users);
    }
}

function getUserPermissions() {
    $db  = getDB();
    $uid = (int)($_GET['user_id'] ?? 0);
    $stmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id=?");
    $stmt->execute([$uid]);
    jsonResponse(true, array_column($stmt->fetchAll(), 'category_id'));
}

function createUser() {
    $db       = getDB();
    $name     = trim($_POST['name'] ?? '');
    $name_en  = trim($_POST['name_en'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId   = (int)($_POST['role_id'] ?? 0);

    if (empty($name) || empty($username) || empty($password) || !$roleId) {
        jsonResponse(false, null, 'يرجى تعبئة جميع الحقول', 400);
    }

    // Check unique username
    $chk = $db->prepare("SELECT id FROM users WHERE username=?");
    $chk->execute([$username]);
    if ($chk->fetch()) jsonResponse(false, null, 'اسم المستخدم مستخدم مسبقاً', 400);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $can_print   = isset($_POST['can_print']) ? (int)$_POST['can_print'] : 1;
    $print_type  = in_array($_POST['print_type'] ?? '', ['network','bluetooth','chef']) ? $_POST['print_type'] : 'network';
    $permissions = $_POST['permissions'] ?? null;
    $printer_mac = trim($_POST['printer_mac'] ?? '');
    $warehouse_id = trim($_POST['warehouse_id'] ?? '');
    if (empty($warehouse_id)) $warehouse_id = null;
    
    $stmt = $db->prepare("INSERT INTO users (name, name_en, username, password, role_id, can_print, print_type, permissions, printer_mac, warehouse_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$name, $name_en ?: null, $username, $hash, $roleId, $can_print, $print_type, $permissions, $printer_mac ?: null, $warehouse_id]);
    jsonResponse(true, ['id' => $db->lastInsertId()], 'تم إضافة المستخدم بنجاح');
}

function updateUser() {
    $db       = getDB();
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $name_en  = trim($_POST['name_en'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $roleId   = (int)($_POST['role_id'] ?? 0);
    $active   = (int)($_POST['is_active'] ?? 1);

    if (!$id || empty($name) || empty($username) || !$roleId) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }
    $can_print   = isset($_POST['can_print']) ? (int)$_POST['can_print'] : 1;
    $print_type  = in_array($_POST['print_type'] ?? '', ['network','bluetooth','chef']) ? $_POST['print_type'] : 'network';
    $permissions = $_POST['permissions'] ?? null;
    $printer_mac = trim($_POST['printer_mac'] ?? '');
    $warehouse_id = trim($_POST['warehouse_id'] ?? '');
    if (empty($warehouse_id)) $warehouse_id = null;

    if ($id === 1) jsonResponse(false, null, 'لا يمكن تعديل المدير الرئيسي', 400);

    $stmt = $db->prepare("UPDATE users SET name=?, name_en=?, username=?, role_id=?, is_active=?, can_print=?, print_type=?, permissions=?, printer_mac=?, warehouse_id=? WHERE id=?");
    $stmt->execute([$name, $name_en ?: null, $username, $roleId, $active, $can_print, $print_type, $permissions, $printer_mac ?: null, $warehouse_id, $id]);
    jsonResponse(true, null, 'تم تعديل بيانات المستخدم');
}

function resetPassword() {
    $db       = getDB();
    $input    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id       = (int)($input['id'] ?? 0);
    $password = $input['password'] ?? '';

    if (!$id || strlen($password) < 6) jsonResponse(false, null, 'يرجى إدخال كلمة مرور صحيحة (6 أحرف على الأقل)', 400);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
    jsonResponse(true, null, 'تم تغيير كلمة المرور بنجاح');
}

function savePermissions() {
    $db     = getDB();
    $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $userId = (int)($input['user_id'] ?? 0);
    $catIds = $input['category_ids'] ?? [];

    if (!$userId) jsonResponse(false, null, 'معرف المستخدم غير صالح', 400);

    $db->prepare("DELETE FROM user_category_permissions WHERE user_id=?")->execute([$userId]);
    if (!empty($catIds)) {
        $insertStmt = $db->prepare("INSERT INTO user_category_permissions (user_id, category_id) VALUES (?,?)");
        foreach ($catIds as $cid) {
            $insertStmt->execute([$userId, (int)$cid]);
        }
    }
    jsonResponse(true, null, 'تم حفظ الصلاحيات بنجاح');
}

function deleteUser() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if ($id === 1) jsonResponse(false, null, 'لا يمكن حذف المدير الرئيسي', 400);
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    jsonResponse(true, null, 'تم حذف المستخدم');
}
