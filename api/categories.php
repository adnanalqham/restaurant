<?php
require_once __DIR__ . '/../config/db.php';
// Basic auth for all endpoints, role check inside actions if needed
requireAuth(); 

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getCategories();
        break;
    case 'POST':
        requireAuth(['admin']);
        if ($action === 'delete') {
            deleteCategory();
        } else {
            $action === 'update' ? updateCategory() : createCategory();
        }
        break;
    case 'DELETE':
        requireAuth(['admin']);
        deleteCategory();
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getCategories() {
    session_write_close(); // Release lock for read-only request
    $db = getDB();
    $all = $_GET['all'] ?? false;
    $sql = "SELECT * FROM categories" . (!$all ? " WHERE is_active=1" : "") . " ORDER BY sort_order, name_ar";
    $rows = $db->query($sql)->fetchAll();
    jsonResponse(true, $rows);
}

function createCategory() {
    $db = getDB();
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $icon    = trim($_POST['icon'] ?? '🍽️');
    $sort    = (int)($_POST['sort_order'] ?? 0);

    if (empty($name_ar) || empty($name_en)) {
        jsonResponse(false, null, 'يرجى إدخال اسم الفئة بالعربية والإنجليزية', 400);
    }

    $stmt = $db->prepare("INSERT INTO categories (name_ar, name_en, icon, sort_order) VALUES (?,?,?,?)");
    $stmt->execute([$name_ar, $name_en, $icon, $sort]);
    $id = $db->lastInsertId();
    pushEvent('category_updated', ['action' => 'created', 'id' => $id]);
    logActivity("إضافة فئة", "تمت إضافة فئة جديدة: $name_ar");
    jsonResponse(true, ['id' => $id], 'تمت إضافة الفئة بنجاح');
}

function updateCategory() {
    $db = getDB();
    $id      = (int)($_POST['id'] ?? 0);
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $icon    = trim($_POST['icon'] ?? '🍽️');
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $active  = (int)($_POST['is_active'] ?? 1);

    if (!$id || empty($name_ar) || empty($name_en)) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }

    $stmt = $db->prepare("UPDATE categories SET name_ar=?, name_en=?, icon=?, sort_order=?, is_active=? WHERE id=?");
    $stmt->execute([$name_ar, $name_en, $icon, $sort, $active, $id]);
    pushEvent('category_updated', ['action' => 'updated', 'id' => $id]);
    logActivity("تحديث فئة", "تعديل بيانات فئة '$name_ar'");
    jsonResponse(true, null, 'تم تعديل الفئة بنجاح');
}

function deleteCategory() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    try {
        $db->beginTransaction();

        // Get all items in this category to delete their images and order refs
        $stmt = $db->prepare("SELECT id, image FROM items WHERE category_id=?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();

        foreach ($items as $item) {
            if ($item['image'] && file_exists(UPLOAD_DIR . $item['image'])) {
                @unlink(UPLOAD_DIR . $item['image']);
            }
            // Delete order refs for these items
            $db->prepare("DELETE FROM order_items WHERE item_id=?")->execute([$item['id']]);
        }

        // Delete items in category
        $db->prepare("DELETE FROM items WHERE category_id=?")->execute([$id]);

        // Delete permissions refs
        $db->prepare("DELETE FROM user_category_permissions WHERE category_id=?")->execute([$id]);

        // Finally delete category
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);

        $db->commit();
        pushEvent('category_updated', ['action' => 'deleted', 'id' => $id]);
        logActivity("حذف فئة", "تم حذف فئة (المعرف: $id) وجميع متعلقاتها");
        jsonResponse(true, null, 'تم حذف الفئة وجميع متعلقاتها بنجاح');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonResponse(false, null, 'خطأ أثناء حذف الفئة: ' . $e->getMessage(), 500);
    }
}
