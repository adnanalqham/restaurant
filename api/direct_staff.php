<?php
/**
 * api/direct_staff.php - إدارة قائمة المباشرين
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

// Always output JSON
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user   = getCurrentUser();

// ── GET: list staff ──────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $db = getDB();
        $onlyActive = (($_GET['active'] ?? '1') === '1');
        if ($onlyActive) {
            $rows = $db->query("SELECT id, name, is_active, sort_order FROM direct_staff WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $db->query("SELECT id, name, is_active, sort_order FROM direct_staff ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
    }
    exit;
}

// ── Admin-only POST actions ──────────────────────────────────────────────────
if (!in_array($user['role'], ['admin', 'accountant'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db    = getDB();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($input)) $input = $_POST;

try {
    switch ($action) {
        case 'add':
            $name = trim($input['name'] ?? '');
            $sort = (int)($input['sort_order'] ?? 0);
            if (!$name) { echo json_encode(['success' => false, 'message' => 'الاسم مطلوب']); exit; }

            // Check duplicate
            $chk = $db->prepare("SELECT id FROM direct_staff WHERE name = ?");
            $chk->execute([$name]);
            if ($chk->fetch()) { echo json_encode(['success' => false, 'message' => 'الاسم موجود مسبقاً']); exit; }

            $db->prepare("INSERT INTO direct_staff (name, sort_order) VALUES (?, ?)")->execute([$name, $sort]);
            logActivity('إضافة مباشر', "الاسم: $name");
            echo json_encode(['success' => true, 'data' => ['id' => $db->lastInsertId()], 'message' => 'تم إضافة المباشر بنجاح']);
            break;

        case 'edit':
            $id   = (int)($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $sort = (int)($input['sort_order'] ?? 0);
            if (!$id || !$name) { echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']); exit; }
            $db->prepare("UPDATE direct_staff SET name=?, sort_order=? WHERE id=?")->execute([$name, $sort, $id]);
            logActivity('تعديل مباشر', "ID: $id | الاسم: $name");
            echo json_encode(['success' => true, 'message' => 'تم التعديل بنجاح']);
            break;

        case 'toggle':
            $id = (int)($input['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'معرف غير صالح']); exit; }
            $db->prepare("UPDATE direct_staff SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة']);
            break;

        case 'delete':
            $id = (int)($input['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'معرف غير صالح']); exit; }
            // Check if used
            $used = $db->prepare("SELECT COUNT(*) FROM orders WHERE direct_name = (SELECT name FROM direct_staff WHERE id=?)");
            $used->execute([$id]);
            if ($used->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن الحذف: مرتبط بطلبات. أوقفه بدلاً من الحذف.']);
                exit;
            }
            $db->prepare("DELETE FROM direct_staff WHERE id=?")->execute([$id]);
            logActivity('حذف مباشر', "ID: $id");
            echo json_encode(['success' => true, 'message' => 'تم الحذف']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
}
exit;
