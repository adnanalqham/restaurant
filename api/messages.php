<?php
require_once __DIR__ . '/../config/db.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user   = getCurrentUser();

switch ($method) {
    case 'GET':
        $action === 'unread_count' ? getUnreadCount() : getMessages();
        break;
    case 'POST':
        sendMessage();
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getMessages() {
    session_write_close();
    $db   = getDB();
    $user = getCurrentUser();
    $limit = (int)($_GET['limit'] ?? 50);

    $stmt = $db->prepare("
        SELECT m.*, s.name as sender_name, r.name as receiver_name
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        LEFT JOIN users r ON m.receiver_id = r.id
        WHERE m.receiver_id = ? OR m.receiver_id IS NULL OR m.sender_id = ?
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user['id'], $user['id'], $limit]);
    $messages = $stmt->fetchAll();

    // Mark as read
    $db->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND is_read=0")->execute([$user['id']]);

    jsonResponse(true, array_reverse($messages));
}

function getUnreadCount() {
    session_write_close();
    $db   = getDB();
    $user = getCurrentUser();
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE (receiver_id=? OR receiver_id IS NULL) AND sender_id != ? AND is_read=0");
    $stmt->execute([$user['id'], $user['id']]);
    jsonResponse(true, ['count' => (int)$stmt->fetchColumn()]);
}

function sendMessage() {
    $db     = getDB();
    $user   = getCurrentUser();
    $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $msg    = trim($input['message'] ?? '');
    $recvId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : null;

    if (empty($msg)) jsonResponse(false, null, 'الرسالة فارغة', 400);

    $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)");
    $stmt->execute([$user['id'], $recvId, $msg]);
    $msgId = $db->lastInsertId();

    pushEvent('new_message', [
        'id'            => $msgId,
        'message'       => $msg,
        'sender_id'     => $user['id'],
        'sender_name'   => $user['name'],
        'receiver_id'   => $recvId,
        'created_at'    => date('Y-m-d H:i:s'),
    ]);

    jsonResponse(true, ['id' => $msgId], 'تم إرسال الرسالة');
}
