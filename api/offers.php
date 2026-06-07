<?php
require_once __DIR__ . '/../config/db.php';
// Logic: Allow viewing by all roles, but management only by admin
// Moved to switch cases below

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';

switch ($method) {
    case 'GET':
        requireAuth(); // Logged in users can see offers
        if ($type === 'combos') getCombos();
        elseif ($type === 'discounts') getDiscounts();
        else jsonResponse(false, null, 'نوع غير معروف');
        break;
    case 'POST':
        requireAuth(['admin']); // Strictly admin for modifications
        switch ($action) {
            case 'create_combo': createCombo(); break;
            case 'delete_combo': deleteCombo(); break;
            case 'toggle_combo': toggleCombo(); break;
            case 'create_discount': createDiscount(); break;
            case 'delete_discount': deleteDiscount(); break;
            case 'toggle_discount': toggleDiscount(); break;
            default: jsonResponse(false, null, 'Action غير معروف'); break;
        }
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

// ---------------------------------------------------------
// COMBOS (Offers)
// ---------------------------------------------------------

function getCombos() {
    $db = getDB();
    $offers = $db->query("SELECT * FROM offers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch items for each offer
    foreach ($offers as &$offer) {
        $stmt = $db->prepare("
            SELECT oi.id, oi.item_id, oi.quantity, i.name_ar as item_name
            FROM offer_items oi
            JOIN items i ON oi.item_id = i.id
            WHERE oi.offer_id = ?
        ");
        $stmt->execute([$offer['id']]);
        $offer['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Also return available items for the dropdown
    $items = $db->query("SELECT id, name_ar, price FROM items WHERE is_available = 1 ORDER BY name_ar")->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, ['combos' => $offers, 'items' => $items]);
}

function createCombo() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name_ar = trim($input['name_ar'] ?? '');
    $price = (float)($input['price'] ?? 0);
    $items = $input['items'] ?? []; // Array of {item_id, qty}
    
    if (empty($name_ar) || $price <= 0 || empty($items)) {
        jsonResponse(false, null, 'يرجى تعبئة الاسم والسعر واختيار صنف واحد على الأقل', 400);
    }
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO offers (name_ar, price) VALUES (?, ?)");
        $stmt->execute([$name_ar, $price]);
        $offerId = $db->lastInsertId();
        
        $iStmt = $db->prepare("INSERT INTO offer_items (offer_id, item_id, quantity) VALUES (?, ?, ?)");
        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $qty = (int)$item['qty'];
            if ($itemId > 0 && $qty > 0) {
                $iStmt->execute([$offerId, $itemId, $qty]);
            }
        }
        
        $db->commit();
        jsonResponse(true, null, 'تم إضافة الباقة بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'حدث خطأ: ' . $e->getMessage(), 500);
    }
}

function deleteCombo() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'ID مفقود', 400);
    
    $db->prepare("DELETE FROM offers WHERE id=?")->execute([$id]);
    jsonResponse(true, null, 'تم حذف الباقة بنجاح');
}

function toggleCombo() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $status = (int)($input['status'] ?? 0);
    if (!$id) jsonResponse(false, null, 'ID مفقود', 400);
    
    $db->prepare("UPDATE offers SET is_active=? WHERE id=?")->execute([$status, $id]);
    jsonResponse(true, null, 'تم تغيير حالة الباقة');
}

// ---------------------------------------------------------
// DISCOUNTS (Items/Categories)
// ---------------------------------------------------------

function getDiscounts() {
    $db = getDB();
    $discounts = $db->query("SELECT * FROM discounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Resolve names for targets
    foreach ($discounts as &$d) {
        if ($d['type'] === 'item') {
            $stmt = $db->prepare("SELECT name_ar FROM items WHERE id=?");
            $stmt->execute([$d['target_id']]);
            $d['target_name'] = $stmt->fetchColumn() ?: 'صنف محذوف';
        } else if ($d['type'] === 'category') {
            $stmt = $db->prepare("SELECT name_ar FROM categories WHERE id=?");
            $stmt->execute([$d['target_id']]);
            $d['target_name'] = $stmt->fetchColumn() ?: 'فئة محذوفة';
        }
    }
    
    $items = $db->query("SELECT id, name_ar FROM items WHERE is_available = 1 ORDER BY name_ar")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $db->query("SELECT id, name_ar FROM categories WHERE is_active = 1 ORDER BY name_ar")->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(true, ['discounts' => $discounts, 'items' => $items, 'categories' => $categories]);
}

function createDiscount() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? ''; // 'item' or 'category'
    $targetId = (int)($input['target_id'] ?? 0);
    $dType = $input['discount_type'] ?? 'fixed'; // 'fixed' or 'percent'
    $dVal = (float)($input['discount_value'] ?? 0);
    
    if (!in_array($type, ['item', 'category']) || !$targetId || $dVal <= 0) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }
    
    // Check if there is already an active discount for this target
    $check = $db->prepare("SELECT id FROM discounts WHERE type=? AND target_id=? AND is_active=1");
    $check->execute([$type, $targetId]);
    if ($check->fetch()) {
        jsonResponse(false, null, 'يوجد تخفيض فعّال مسبقاً لهذا العنصر، قم بإيقافه أولاً', 400);
    }
    
    $stmt = $db->prepare("INSERT INTO discounts (type, target_id, discount_type, discount_value) VALUES (?, ?, ?, ?)");
    $stmt->execute([$type, $targetId, $dType, $dVal]);
    
    jsonResponse(true, null, 'تم تطبيق التخفيض بنجاح');
}

function deleteDiscount() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'ID مفقود', 400);
    
    $db->prepare("DELETE FROM discounts WHERE id=?")->execute([$id]);
    jsonResponse(true, null, 'تم حذف التخفيض');
}

function toggleDiscount() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $status = (int)($input['status'] ?? 0);
    if (!$id) jsonResponse(false, null, 'ID مفقود', 400);
    
    $db->prepare("UPDATE discounts SET is_active=? WHERE id=?")->execute([$status, $id]);
    jsonResponse(true, null, 'تم تغيير حالة التخفيض');
}
