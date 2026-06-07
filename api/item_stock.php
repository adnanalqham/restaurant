<?php
/**
 * api/item_stock.php
 * API لإدارة رصيد الأصناف
 */
require_once __DIR__ . '/../config/db.php';
requireAuth();

$user   = getCurrentUser();
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// Permission helpers
function hasStockManagement(array $user): bool {
    if ($user['role'] === 'admin') return true;
    $perms = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
    return is_array($perms) && in_array('stock_management', $perms);
}

function canViewStock(array $user): bool {
    if ($user['role'] === 'admin') return true;
    $perms = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
    if (!is_array($perms)) return false;
    return in_array('stock_management', $perms) || in_array('show_stock', $perms);
}

// Route by action + permission
switch ($action) {
    case 'list':
        // Readable by anyone with show_stock or stock_management
        if (!canViewStock($user)) jsonResponse(false, null, 'ليس لديك صلاحية عرض الرصيد', 403);
        getStockList();
        break;
    case 'set':
        if (!hasStockManagement($user)) jsonResponse(false, null, 'ليس لديك صلاحية تعديل الرصيد', 403);
        setStock();
        break;
    case 'log':
        if (!hasStockManagement($user)) jsonResponse(false, null, 'ليس لديك صلاحية عرض السجل', 403);
        getStockLog();
        break;
    default:
        jsonResponse(false, null, 'إجراء غير معروف', 400);
}

// ─── GET: List all items with their stock ────────────────────────────────────
function getStockList() {
    $db = getDB();
    $stmt = $db->query("
        SELECT i.id, i.name_ar, i.name_en, i.price, i.is_available,
               c.name_ar AS category_name,
               COALESCE(s.stock_qty, 0) AS stock_qty,
               s.updated_at,
               s.updated_by
        FROM items i
        JOIN categories c ON i.category_id = c.id
        LEFT JOIN item_stock s ON s.item_id = i.id
        ORDER BY c.name_ar ASC, i.name_ar ASC
    ");
    jsonResponse(true, $stmt->fetchAll());
}

// ─── POST: Set/Add/Adjust stock for an item ──────────────────────────────────
function setStock() {
    $db   = getDB();
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $itemId    = (int)($input['item_id'] ?? 0);
    $qty       = (float)($input['qty'] ?? 0);
    $actionType = $input['action_type'] ?? 'set'; // set | add | deduct
    $note      = trim($input['note'] ?? '');

    if (!$itemId) jsonResponse(false, null, 'يرجى تحديد الصنف', 400);
    if ($qty < 0 && $actionType !== 'deduct') jsonResponse(false, null, 'الكمية لا يمكن أن تكون سالبة', 400);

    // Fetch item name
    $iStmt = $db->prepare("SELECT name_ar FROM items WHERE id=?");
    $iStmt->execute([$itemId]);
    $item = $iStmt->fetch();
    if (!$item) jsonResponse(false, null, 'الصنف غير موجود', 404);

    // Fetch current stock
    $sStmt = $db->prepare("SELECT stock_qty FROM item_stock WHERE item_id=?");
    $sStmt->execute([$itemId]);
    $current = $sStmt->fetch();
    $qtyBefore = $current ? (float)$current['stock_qty'] : 0;

    // Calculate new qty
    if ($actionType === 'set') {
        $qtyAfter  = $qty;
        $qtyChange = $qty - $qtyBefore;
    } elseif ($actionType === 'add') {
        $qtyAfter  = $qtyBefore + $qty;
        $qtyChange = $qty;
    } elseif ($actionType === 'deduct') {
        $qtyAfter  = max(0, $qtyBefore - $qty);
        $qtyChange = -$qty;
    } else {
        jsonResponse(false, null, 'نوع العملية غير صالح', 400);
    }

    $db->beginTransaction();
    try {
        // Upsert stock record
        $db->prepare("
            INSERT INTO item_stock (item_id, stock_qty, updated_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE stock_qty=VALUES(stock_qty), updated_by=VALUES(updated_by)
        ")->execute([$itemId, $qtyAfter, $user['id']]);

        // Log the action
        $db->prepare("
            INSERT INTO item_stock_log (item_id, item_name_ar, action_type, qty_before, qty_change, qty_after, note, user_id, user_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$itemId, $item['name_ar'], $actionType, $qtyBefore, $qtyChange, $qtyAfter, $note, $user['id'], $user['name']]);

        $db->commit();
        logActivity("تعديل رصيد صنف", "الصنف: {$item['name_ar']} | النوع: {$actionType} | من {$qtyBefore} إلى {$qtyAfter}");
        jsonResponse(true, ['new_qty' => $qtyAfter], 'تم تحديث الرصيد بنجاح');
    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, null, 'خطأ في التحديث: ' . $e->getMessage(), 500);
    }
}

// ─── GET: Stock log ──────────────────────────────────────────────────────────
function getStockLog() {
    $db     = getDB();
    $itemId = (int)($_GET['item_id'] ?? 0);
    $from   = $_GET['from'] ?? '';
    $to     = $_GET['to'] ?? '';
    // If date filters are active, allow more records, else default to 50
    $limit  = ($from || $to) ? 2000 : min((int)($_GET['limit'] ?? 50), 200);

    $sql    = "SELECT * FROM item_stock_log WHERE 1=1";
    $params = [];
    if ($itemId) {
        $sql    .= " AND item_id=?";
        $params[] = $itemId;
    }
    if ($from) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $from;
    }
    if ($to) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $to;
    }
    $sql .= " ORDER BY created_at DESC LIMIT $limit";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, $stmt->fetchAll());
}
