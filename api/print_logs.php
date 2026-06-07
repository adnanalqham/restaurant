<?php
/**
 * api/print_logs.php
 * Logs print results for monitoring and debugging.
 */
require_once __DIR__ . '/../config/db.php';
startSession();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $orderId = (int)($input['order_id'] ?? 0);
    $status  = $input['status'] ?? 'failed';
    $error   = $input['error_message'] ?? null;
    $type    = $input['printer_type'] ?? 'bluetooth';

    if (!$orderId) die(json_encode(['success'=>false]));

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO print_logs (user_id, order_id, printer_type, status, error_message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $currentUser ? $currentUser['id'] : 0,
        $orderId,
        $type,
        $status,
        $error
    ]);

    echo json_encode(['success'=>true]);
}
