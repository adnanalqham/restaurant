<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(); // Open to all logged-in users

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'change_password') {
    $db = getDB();
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $oldPass = $input['old_password'] ?? '';
    $newPass = $input['new_password'] ?? '';
    
    if (empty($oldPass) || empty($newPass)) {
        jsonResponse(false, null, 'يرجى إدخال كلمة المرور القديمة والجديدة', 400);
    }
    
    if (strlen($newPass) < 6) {
        jsonResponse(false, null, 'كلمة المرور يجب أن تكون 6 أحرف على الأقل', 400);
    }
    
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $currentUserRec = $stmt->fetch();
    
    if (!password_verify($oldPass, $currentUserRec['password'])) {
        jsonResponse(false, null, 'كلمة المرور القديمة غير صحيحة', 400);
    }
    
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user['id']]);
    
    jsonResponse(true, null, 'تم تغيير كلمة المرور بنجاح');
}

jsonResponse(false, null, 'إجراء غير معروف', 400);
