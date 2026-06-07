<?php
require_once __DIR__ . '/config/db.php';
requireAuth();
$db = getDB();
try {
    $rows = $db->query("SELECT id, name, is_active, sort_order FROM direct_staff ORDER BY sort_order ASC, name ASC")->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => count($rows), 'data' => $rows]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
