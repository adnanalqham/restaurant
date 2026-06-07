<?php
require_once __DIR__ . '/../config/db.php';
// Basic auth for all endpoints, role check inside actions if needed
requireAuth(); 

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getItems();
        break;
    case 'POST':
        requireAuth(['admin']);
        if ($action === 'delete') {
            deleteItem();
        } else {
            $action === 'update' ? updateItem() : createItem();
        }
        break;
    case 'DELETE':
        requireAuth(['admin']);
        deleteItem();
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getItems() {
    session_write_close(); // Release lock for read-only request
    $db = getDB();
    $catId = (int)($_GET['category_id'] ?? 0);
    $all   = $_GET['all'] ?? false;

    $sql = "SELECT i.*, c.name_ar as cat_name_ar, c.name_en as cat_name_en 
            FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE 1=1";
    $params = [];
    if ($catId) { $sql .= " AND i.category_id=?"; $params[] = $catId; }
    if (!$all)  { $sql .= " AND i.is_available=1"; }
    $sql .= " ORDER BY i.sort_order, i.name_ar";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch active discounts
    $itemDiscounts = [];
    $catDiscounts = [];
    try {
        $discountStmt = $db->query("SELECT * FROM discounts WHERE is_active=1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())");
        foreach ($discountStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            if ($d['type'] === 'item') {
                $itemDiscounts[$d['target_id']] = $d;
            } else if ($d['type'] === 'category') {
                $catDiscounts[$d['target_id']] = $d;
            }
        }
    } catch (Exception $e) { /* Ignore if table doesn't exist yet */ }

    // Apply discounts
    foreach ($rows as &$item) {
        $basePrice = (float)$item['price'];
        $item['original_price'] = $basePrice;
        $activeDiscount = null;

        if (isset($itemDiscounts[$item['id']])) {
            $activeDiscount = $itemDiscounts[$item['id']];
        } else if (isset($catDiscounts[$item['category_id']])) {
            $activeDiscount = $catDiscounts[$item['category_id']];
        }

        if ($activeDiscount) {
            $v = (float)$activeDiscount['discount_value'];
            if ($activeDiscount['discount_type'] === 'percent') {
                $item['discounted_price'] = round($basePrice - ($basePrice * ($v / 100)), 2);
            } else {
                $item['discounted_price'] = round(max(0, $basePrice - $v), 2);
            }
            $item['discount_label'] = $activeDiscount['label'] ?: 'تخفيض خاص';
        } else {
            $item['discounted_price'] = $basePrice;
        }
    }

    jsonResponse(true, $rows);
}

function createItem() {
    $db = getDB();
    $catId   = (int)($_POST['category_id'] ?? 0);
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $price   = (float)($_POST['price'] ?? 0);
    $desc_ar = trim($_POST['description_ar'] ?? '');
    $desc_en = trim($_POST['description_en'] ?? '');
    $item_num = trim($_POST['item_number'] ?? '');
    $sort    = (int)($_POST['sort_order'] ?? 0);

    if (!$catId || empty($name_ar) || empty($name_en) || $price < 0) {
        jsonResponse(false, null, 'يرجى تعبئة جميع الحقول المطلوبة', 400);
    }

    if (!empty($item_num)) {
        $check = $db->prepare("SELECT COUNT(*) FROM items WHERE item_number = ?");
        $check->execute([$item_num]);
        if ($check->fetchColumn() > 0) {
            jsonResponse(false, null, 'خطأ: رقم الصنف هذا مدخل مسبقاً لصنف آخر. يرجى اختيار رقم غير مكرر.', 400);
        }
    }

    $has_sizes = isset($_POST['has_sizes']) && $_POST['has_sizes'] == '1' ? 1 : 0;
    $sizes_json = null;
    if ($has_sizes && !empty($_POST['sizes'])) {
        $sizes_arr = json_decode($_POST['sizes'], true);
        if (is_array($sizes_arr)) {
            $sizes_json = json_encode($sizes_arr, JSON_UNESCAPED_UNICODE);
        } else {
            $has_sizes = 0;
        }
    }

    $has_addons = isset($_POST['has_addons']) && $_POST['has_addons'] == '1' ? 1 : 0;
    $addons_json = null;
    if ($has_addons && !empty($_POST['addons'])) {
        $addons_arr = json_decode($_POST['addons'], true);
        if (is_array($addons_arr)) {
            $addons_json = json_encode($addons_arr, JSON_UNESCAPED_UNICODE);
        } else {
            $has_addons = 0;
        }
    }

    $imagePath = handleImageUpload();

    try {
        $stmt = $db->prepare("INSERT INTO items (category_id, item_number, name_ar, name_en, price, description_ar, description_en, image, sort_order, is_available, has_sizes, sizes, has_addons, addons) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?,?)");
        $stmt->execute([$catId, $item_num, $name_ar, $name_en, $price, $desc_ar, $desc_en, $imagePath, $sort, $has_sizes, $sizes_json, $has_addons, $addons_json]);
        $id = $db->lastInsertId();
        
        // Audit Log: Create
        logItemAudit($id, 'create', 'all', null, json_encode($_POST, JSON_UNESCAPED_UNICODE));
        
        pushEvent('menu_updated', ['action' => 'created', 'id' => $id, 'category_id' => $catId]);
        logActivity("إضافة صنف", "تمت إضافة '$name_ar' في فئة " . ($catId));
        jsonResponse(true, ['id' => $id], 'تمت إضافة الصنف بنجاح');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            jsonResponse(false, null, 'خطأ: أعمدة ناقصة في قاعدة البيانات. الرجاء تشغيل ملفات التحديث migrate_sizes.php و migrate_addons.php', 500);
        } else {
            jsonResponse(false, null, 'خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
        }
    }
}

function updateItem() {
    $db = getDB();
    $id      = (int)($_POST['id'] ?? 0);
    $catId   = (int)($_POST['category_id'] ?? 0);
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $price   = (float)($_POST['price'] ?? 0);
    $desc_ar = trim($_POST['description_ar'] ?? '');
    $desc_en = trim($_POST['description_en'] ?? '');
    $item_num = trim($_POST['item_number'] ?? '');
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $avail   = (int)($_POST['is_available'] ?? 1);

    if (!$id || !$catId || empty($name_ar) || empty($name_en)) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }

    if (!empty($item_num)) {
        $check = $db->prepare("SELECT COUNT(*) FROM items WHERE item_number = ? AND id != ?");
        $check->execute([$item_num, $id]);
        if ($check->fetchColumn() > 0) {
            jsonResponse(false, null, 'خطأ: رقم الصنف هذا مدخل مسبقاً لصنف آخر. يرجى اختيار رقم غير مكرر.', 400);
        }
    }

    // Existing data for audit
    $existing = $db->prepare("SELECT * FROM items WHERE id=?");
    $existing->execute([$id]);
    $oldItem = $existing->fetch();
    if (!$oldItem) jsonResponse(false, null, 'الصنف غير موجود', 404);
    
    $imagePath = $oldItem['image'] ?? '';

    if (!empty($_FILES['image']['tmp_name'])) {
        // Delete old image
        if ($imagePath && file_exists(UPLOAD_DIR . $imagePath)) {
            unlink(UPLOAD_DIR . $imagePath);
        }
        $imagePath = handleImageUpload();
    }

    $has_sizes = isset($_POST['has_sizes']) && $_POST['has_sizes'] == '1' ? 1 : 0;
    $sizes_json = null;
    if ($has_sizes && !empty($_POST['sizes'])) {
        $sizes_arr = json_decode($_POST['sizes'], true);
        if (is_array($sizes_arr)) {
            $sizes_json = json_encode($sizes_arr, JSON_UNESCAPED_UNICODE);
        } else {
            $has_sizes = 0;
        }
    }

    $has_addons = isset($_POST['has_addons']) && $_POST['has_addons'] == '1' ? 1 : 0;
    $addons_json = null;
    if ($has_addons && !empty($_POST['addons'])) {
        $addons_arr = json_decode($_POST['addons'], true);
        if (is_array($addons_arr)) {
            $addons_json = json_encode($addons_arr, JSON_UNESCAPED_UNICODE);
        } else {
            $has_addons = 0;
        }
    }

    try {
        $stmt = $db->prepare("UPDATE items SET category_id=?, item_number=?, name_ar=?, name_en=?, price=?, description_ar=?, description_en=?, image=?, sort_order=?, is_available=?, has_sizes=?, sizes=?, has_addons=?, addons=? WHERE id=?");
        $stmt->execute([$catId, $item_num, $name_ar, $name_en, $price, $desc_ar, $desc_en, $imagePath, $sort, $avail, $has_sizes, $sizes_json, $has_addons, $addons_json, $id]);
        
        // Audit Log: Check for changes
        $user = getCurrentUser();
        if ((float)$oldItem['price'] != $price) {
            logItemAudit($id, 'update', 'price', $oldItem['price'], $price);
        }
        if ($oldItem['name_ar'] != $name_ar) {
            logItemAudit($id, 'update', 'name_ar', $oldItem['name_ar'], $name_ar);
        }
        if ($oldItem['is_available'] != $avail) {
            logItemAudit($id, 'update', 'is_available', $oldItem['is_available'], $avail);
        }

        pushEvent('menu_updated', ['action' => 'updated', 'id' => $id, 'category_id' => $catId]);
        logActivity("تحديث صنف", "تعديل بيانات '$name_ar'");
        jsonResponse(true, null, 'تم تعديل الصنف بنجاح');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            jsonResponse(false, null, 'خطأ: أعمدة الترقية مفقودة في قاعدة البيانات. الرجاء فتح وتحديث الروابط migrate_sizes.php و migrate_addons.php على المتصفح أولاً.', 500);
        } else {
            jsonResponse(false, null, 'خطأ في قاعدة البيانات: ' . $e->getMessage(), 500);
        }
    }
}

function deleteItem() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    try {
        $db->beginTransaction();

        // Get image to delete
        $row = $db->prepare("SELECT image FROM items WHERE id=?");
        $row->execute([$id]);
        $item = $row->fetch();
        if ($item && $item['image'] && file_exists(UPLOAD_DIR . $item['image'])) {
            @unlink(UPLOAD_DIR . $item['image']);
        }

        // Wipe from roots: Delete references in order_items
        $db->prepare("DELETE FROM order_items WHERE item_id=?")->execute([$id]);

        // Finally delete the item
        $db->prepare("DELETE FROM items WHERE id=?")->execute([$id]);

        // Audit Log: Delete
        logItemAudit($id, 'delete', 'all', json_encode($item, JSON_UNESCAPED_UNICODE), null);

        $db->commit();
        pushEvent('menu_updated', ['action' => 'deleted', 'id' => $id]);
        logActivity("حذف صنف نهائياً", "تم حذف صنف (المعرف: $id) من الجذور");
        jsonResponse(true, null, 'تم حذف الصنف من الجذور بنجاح');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonResponse(false, null, 'خطأ أثناء الحذف: ' . $e->getMessage(), 500);
    }
}

function handleImageUpload(): string {
    if (empty($_FILES['image']['tmp_name'])) return '';
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $ftype = mime_content_type($_FILES['image']['tmp_name']);
    if (!in_array($ftype, $allowed)) {
        jsonResponse(false, null, 'نوع الملف غير مدعوم. الأنواع المدعومة: jpg, png, webp', 400);
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        jsonResponse(false, null, 'حجم الصورة كبير جداً (الحد الأقصى 5MB)', 400);
    }
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('item_') . '.' . $ext;
    move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename);
    return $filename;
}

function logItemAudit($itemId, $type, $field, $old, $new) {
    try {
        $db = getDB();
        $user = getCurrentUser();
        $stmt = $db->prepare("INSERT INTO item_audit_log (item_id, user_id, action_type, field_name, old_value, new_value) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$itemId, $user['id'], $type, $field, $old, $new]);
    } catch (Exception $e) {
        // Silent fail for audit log to not break main flow
    }
}
