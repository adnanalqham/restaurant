<?php
require_once __DIR__ . '/../config/db.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getWallets();
        break;
    case 'POST':
        requireAuth(['admin']);
        if ($action === 'delete') deleteWallet();
        elseif ($action === 'update') updateWallet();
        else createWallet();
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getWallets() {
    session_write_close();
    $db  = getDB();
    $all = $_GET['all'] ?? false;
    $sql = "SELECT * FROM wallets" . (!$all ? " WHERE is_active=1" : "") . " ORDER BY sort_order, name";
    $rows = $db->query($sql)->fetchAll();
    jsonResponse(true, $rows);
}

function createWallet() {
    $db   = getDB();
    $name = trim($_POST['name'] ?? '');
    $num  = trim($_POST['account_number'] ?? '');
    $sort = (int)($_POST['sort_order'] ?? 0);
    if (empty($name) || empty($num)) jsonResponse(false, null, 'يرجى إدخال اسم ورقم المحفظة', 400);
    $db->prepare("INSERT INTO wallets (name, account_number, sort_order) VALUES (?,?,?)")->execute([$name, $num, $sort]);
    jsonResponse(true, ['id' => $db->lastInsertId()], 'تمت إضافة المحفظة بنجاح');
}

function updateWallet() {
    $db     = getDB();
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $num    = trim($_POST['account_number'] ?? '');
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $active = (int)($_POST['is_active'] ?? 1);
    if (!$id || empty($name) || empty($num)) jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    $db->prepare("UPDATE wallets SET name=?, account_number=?, sort_order=?, is_active=? WHERE id=?")->execute([$name, $num, $sort, $active, $id]);
    jsonResponse(true, null, 'تم تحديث المحفظة بنجاح');
}

function deleteWallet() {
    $db    = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id    = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);
    $db->prepare("DELETE FROM wallets WHERE id=?")->execute([$id]);
    jsonResponse(true, null, 'تم حذف المحفظة');
}
