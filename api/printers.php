<?php
/**
 * api/printers.php
 * Handles CRUD operations for the printers table.
 */
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin']); // Restricted to Admin

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getPrinters();
        break;
    case 'POST':
        if ($action === 'delete') deletePrinter();
        else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id']) && $input['id'] > 0) updatePrinter();
            else createPrinter();
        }
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getPrinters() {
    $db = getDB();
    $rows = $db->query("SELECT * FROM printers ORDER BY name")->fetchAll();
    jsonResponse(true, $rows);
}

function createPrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $name         = trim($input['name'] ?? '');
    $ip           = trim($input['ip'] ?? '');
    $port         = (int)($input['port'] ?? 9100);
    $type         = $input['type'] ?? 'cashier';
    $windows_name = trim($input['windows_name'] ?? '');

    if (empty($name)) {
        jsonResponse(false, null, 'اسم الطابعة مطلوب', 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO printers (name, ip, port, type, windows_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $ip ?: '', $port, $type, $windows_name]);
        $id = $db->lastInsertId();
        logActivity("إضافة طابعة", "تمت إضافة طابعة جديدة: $name");
        jsonResponse(true, ['id' => $id], 'تمت إضافة الطابعة بنجاح');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

function updatePrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id           = (int)($input['id'] ?? 0);
    $name         = trim($input['name'] ?? '');
    $ip           = trim($input['ip'] ?? '');
    $port         = (int)($input['port'] ?? 9100);
    $type         = $input['type'] ?? 'cashier';
    $windows_name = trim($input['windows_name'] ?? '');

    if (!$id || empty($name)) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }

    try {
        $stmt = $db->prepare("UPDATE printers SET name=?, ip=?, port=?, type=?, windows_name=? WHERE id=?");
        $stmt->execute([$name, $ip, $port, $type, $windows_name, $id]);
        logActivity("تحديث طابعة", "تعديل بيانات الطابعة: $name");
        jsonResponse(true, null, 'تم تحديث بيانات الطابعة');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

function deletePrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    try {
        $stmt = $db->prepare("DELETE FROM printers WHERE id=?");
        $stmt->execute([$id]);
        logActivity("حذف طابعة", "تم حذف الطابعة (معرف: $id)");
        jsonResponse(true, null, 'تم حذف الطابعة بنجاح');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: لا يمكن حذف الطابعة لارتباطها بفئات حالية', 500);
    }
}
