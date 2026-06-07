<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin', 'accountant']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getLogs();
} else {
    jsonResponse(false, null, 'Method not allowed', 405);
}

function getLogs() {
    $db = getDB();
    $limit  = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    $sort   = ($_GET['sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    $stmt = $db->prepare("
        SELECT al.*, u.name as user_name, r.name as user_role
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY al.created_at $sort
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    // Total count for pagination
    $total = $db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
    
    jsonResponse(true, ['logs' => $logs, 'total' => (int)$total]);
}
