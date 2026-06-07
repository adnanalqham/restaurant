<?php
/**
 * api/ingredients.php
 * CRUD API for inventory ingredients
 */
require_once __DIR__ . '/../config/db.php';
startSession();
requireAuth(['admin', 'inventory_monitor']);
$user = getCurrentUser(); // Save user BEFORE closing session
session_write_close();    // Release session lock — prevents blocking parallel requests

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ─── GET: list all ingredients ───────────────────────────────────────────────
if ($method === 'GET' && $action !== 'single') {
    try {
        $stmt = $db->query("SELECT * FROM ingredients ORDER BY ingredient_number ASC, name ASC");
        jsonResponse(true, $stmt->fetchAll());
    } catch (Exception $e) {
        // Table may not exist yet — return empty array gracefully
        jsonResponse(true, []);
    }
}

// ─── GET: single ingredient ───────────────────────────────────────────────────
if ($method === 'GET' && $action === 'single') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);
    $stmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, null, 'المكون غير موجود', 404);
    jsonResponse(true, $row);
}

// ─── POST: create ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === '') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $name  = trim($input['name']  ?? '');
    $unit  = trim($input['unit']  ?? 'gram');
    $num   = trim($input['ingredient_number'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if (!$name) jsonResponse(false, null, 'اسم المكون مطلوب', 400);

    $validUnits = ['gram','kg','piece','liter','ml','cup','tablespoon','other'];
    if (!in_array($unit, $validUnits)) $unit = 'gram';

    // Check duplicate name
    $dup = $db->prepare("SELECT id FROM ingredients WHERE name = ?");
    $dup->execute([$name]);
    if ($dup->fetch()) jsonResponse(false, null, 'مكون بهذا الاسم موجود بالفعل', 400);

    $stmt = $db->prepare("INSERT INTO ingredients (ingredient_number, name, unit, notes) VALUES (?,?,?,?)");
    $stmt->execute([$num ?: null, $name, $unit, $notes ?: null]);
    $newId = (int)$db->lastInsertId();

    logActivity('إضافة مكون', "مكون جديد: $name");
    jsonResponse(true, ['id' => $newId], 'تمت إضافة المكون بنجاح');
}

// ─── POST: bulk import ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'bulk_import') {
    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? [];

    if (empty($items)) jsonResponse(false, null, 'لا توجد بيانات للإضافة', 400);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO ingredients (ingredient_number, name, unit) VALUES (?, ?, ?)");
        $dupStmt = $db->prepare("SELECT id FROM ingredients WHERE name = ?");
        
        $added = 0;
        $errors = [];

        foreach ($items as $idx => $item) {
            $name = trim($item['name'] ?? '');
            $num  = trim($item['ingredient_number'] ?? '');
            $unit = trim($item['unit'] ?? 'gram');
            $rowNum = $idx + 1;

            if (!$name) {
                $errors[] = "السطر $rowNum: اسم المكون مفقود.";
                continue;
            }

            $dupStmt->execute([$name]);
            if ($dupStmt->fetch()) {
                $errors[] = "السطر $rowNum: المكون '$name' موجود مسبقاً.";
                continue;
            }

            $stmt->execute([$num ?: null, $name, $unit]);
            $added++;
        }
        
        $db->commit();
        if ($added > 0) logActivity('إضافة مكونات بالجملة', "تمت إضافة $added مكون جديد");
        
        jsonResponse(true, ['added' => $added, 'errors' => $errors], "تمت إضافة $added مكون بنجاح");
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'خطأ أثناء الإضافة: ' . $e->getMessage(), 500);
    }
}

// ─── POST: update ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id    = (int)($input['id'] ?? 0);
    $name  = trim($input['name']  ?? '');
    $unit  = trim($input['unit']  ?? 'gram');
    $num   = trim($input['ingredient_number'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if (!$id || !$name) jsonResponse(false, null, 'بيانات غير مكتملة', 400);

    $validUnits = ['gram','kg','piece','liter','ml','cup','tablespoon','other'];
    if (!in_array($unit, $validUnits)) $unit = 'gram';

    $stmt = $db->prepare("UPDATE ingredients SET ingredient_number=?, name=?, unit=?, notes=? WHERE id=?");
    $stmt->execute([$num ?: null, $name, $unit, $notes ?: null, $id]);

    logActivity('تعديل مكون', "مكون #$id: $name");
    jsonResponse(true, null, 'تم تحديث المكون بنجاح');
}

// ─── POST: delete ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    requireAuth(['admin']);  // Only admin can delete
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id    = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    // Check if used in any item
    $used = $db->prepare("SELECT COUNT(*) FROM item_ingredients WHERE ingredient_id = ?");
    $used->execute([$id]);
    if ((int)$used->fetchColumn() > 0) {
        jsonResponse(false, null, 'لا يمكن حذف مكون مرتبط بأصناف. احذف الربط أولاً.', 400);
    }

    $db->prepare("DELETE FROM ingredients WHERE id = ?")->execute([$id]);
    logActivity('حذف مكون', "حذف المكون #$id");
    jsonResponse(true, null, 'تم حذف المكون');
}

// ─── GET/POST: item ingredients (for a specific item) ─────────────────────────
if ($method === 'GET' && $action === 'for_item') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) jsonResponse(false, null, 'item_id مطلوب', 400);
    $stmt = $db->prepare("
        SELECT ii.id, ii.item_id, ii.ingredient_id, ii.quantity_per_portion, ii.notes, ii.size_name,
               i.name, i.unit, i.ingredient_number
        FROM item_ingredients ii
        JOIN ingredients i ON ii.ingredient_id = i.id
        WHERE ii.item_id = ?
        ORDER BY i.name ASC
    ");
    $stmt->execute([$itemId]);
    jsonResponse(true, $stmt->fetchAll());
}

if ($method === 'POST' && $action === 'save_item_ingredients') {
    requireAuth(['admin', 'inventory_monitor']);
    $input      = json_decode(file_get_contents('php://input'), true);
    $itemId     = (int)($input['item_id'] ?? 0);
    $ingredients = $input['ingredients'] ?? [];

    if (!$itemId) jsonResponse(false, null, 'item_id مطلوب', 400);

    $db->beginTransaction();
    try {
        // Delete existing links for this item
        $db->prepare("DELETE FROM item_ingredients WHERE item_id = ?")->execute([$itemId]);

        // Insert new links
        $stmt = $db->prepare("INSERT INTO item_ingredients (item_id, ingredient_id, quantity_per_portion, notes, size_name) VALUES (?,?,?,?,?)");
        foreach ($ingredients as $ing) {
            $ingId = (int)($ing['ingredient_id'] ?? 0);
            $qty   = (float)($ing['quantity_per_portion'] ?? 0);
            $note  = trim($ing['notes'] ?? '');
            $sizeName = trim($ing['size_name'] ?? '');
            if ($ingId && $qty > 0) {
                $stmt->execute([$itemId, $ingId, $qty, $note ?: null, $sizeName ?: null]);
            }
        }
        $db->commit();
        logActivity('تحديث مكونات صنف', "الصنف #$itemId");
        jsonResponse(true, null, 'تم حفظ مكونات الصنف');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

jsonResponse(false, null, 'إجراء غير معروف', 400);
