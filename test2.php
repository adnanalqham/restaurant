<?php
require 'config/db.php';
$db=getDB();
$role = 'juice_bar';
$fromDateTime = '2020-01-01 00:00:00';
$toDateTime = '2030-01-01 00:00:00';

$sql = "SELECT i.id, i.name, i.unit, i.item_number,
               (SELECT COALESCE(SUM(ri.issued_qty), 0) 
                FROM inv_request_items ri 
                JOIN inv_requests r ON ri.request_id = r.id 
                JOIN users u ON r.requester_id = u.id
                WHERE ri.item_id = i.id 
                  AND r.status = 'issued' 
                  AND u.role = ?
                  AND r.created_at BETWEEN ? AND ?) as received_in_period,
               (SELECT MAX(r.created_at)
                FROM inv_request_items ri 
                JOIN inv_requests r ON ri.request_id = r.id 
                JOIN users u ON r.requester_id = u.id
                WHERE ri.item_id = i.id AND r.status = 'issued' AND u.role = ?) as last_receipt_date
        FROM inv_items i
        ORDER BY i.name ASC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$role, $fromDateTime, $toDateTime, $role]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
