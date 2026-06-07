<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$category_id = (int)($input['category_id'] ?? 0);

if (!$category_id) {
    jsonResponse(false, null, 'يرجى اختيار الفئة', 400);
}
if (empty($items)) {
    jsonResponse(false, null, 'لا توجد أصناف للإضافة', 400);
}

$db = getDB();

// Verify category exists
$catStmt = $db->prepare("SELECT id, name_ar FROM categories WHERE id=?");
$catStmt->execute([$category_id]);
$cat = $catStmt->fetch();
if (!$cat) {
    jsonResponse(false, null, 'الفئة غير موجودة', 400);
}

$stmt = $db->prepare("INSERT INTO items (category_id, item_number, name_ar, name_en, price, description_ar, description_en, image, sort_order, is_available) VALUES (?,?,?,?,?,?,?,NULL,0,1)");

$added = 0;
$errors = [];

$processed_numbers = [];
foreach ($items as $index => $item) {
    $name_ar    = trim($item['name_ar'] ?? '');
    $name_en    = trim($item['name_en'] ?? '');
    $price      = (float)($item['price'] ?? 0);
    $item_num   = trim($item['item_number'] ?? '');

    if (empty($name_ar) || empty($name_en)) {
        $errors[] = "السطر " . ($index + 1) . ": الاسم العربي أو الإنجليزي فارغ";
        continue;
    }

    if (!empty($item_num)) {
        if (in_array($item_num, $processed_numbers)) {
            $errors[] = "السطر " . ($index + 1) . ": رقم الصنف ($item_num) متكرر في نفس قائمة الاستيراد";
            continue;
        }
        $check = $db->prepare("SELECT COUNT(*) FROM items WHERE item_number = ?");
        $check->execute([$item_num]);
        if ($check->fetchColumn() > 0) {
            $errors[] = "السطر " . ($index + 1) . ": رقم الصنف ($item_num) مدخل مسبقاً لصنف آخر";
            continue;
        }
        $processed_numbers[] = $item_num;
    }

    try {
        $stmt->execute([$category_id, $item_num ?: null, $name_ar, $name_en, $price, '', '']);
        $added++;
    } catch (Exception $e) {
        $errors[] = "السطر " . ($index + 1) . ": " . $e->getMessage();
    }
}

if ($added > 0) {
    pushEvent('menu_updated', ['action' => 'bulk_import', 'count' => $added, 'category_id' => $category_id]);
    logActivity("إضافة جماعية للأصناف", "تمت إضافة $added صنف في فئة '{$cat['name_ar']}'");
}

jsonResponse(true, ['added' => $added, 'errors' => $errors], "تمت إضافة $added صنف بنجاح" . (count($errors) ? ' مع ' . count($errors) . ' أخطاء' : ''));
