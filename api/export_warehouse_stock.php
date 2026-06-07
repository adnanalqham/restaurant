<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(); // Base auth

$warehouse = $_GET['warehouse'] ?? 'main';
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');

$fromDateTime = $fromDate . " 00:00:00";
$toDateTime = $toDate . " 23:59:59";

$db = getDB();
$results = [];

if ($warehouse === 'main') {
    $sql = "SELECT i.name, i.unit, i.current_stock as current_balance, i.item_number,
                   (SELECT COALESCE(SUM(quantity), 0) FROM inv_purchases p WHERE p.item_id = i.id AND p.created_at BETWEEN ? AND ?) as received_in_period,
                   (SELECT MAX(created_at) FROM inv_purchases p WHERE p.item_id = i.id) as last_receipt_date
            FROM inv_items i 
            ORDER BY i.name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$fromDateTime, $toDateTime]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
        $sql = "SELECT i.name, i.unit, i.item_number,
                       (SELECT COALESCE(SUM(ri.issued_qty), 0) 
                        FROM inv_request_items ri 
                        JOIN inv_requests r ON ri.request_id = r.id 
                        JOIN users u ON r.requester_id = u.id
                        WHERE ri.item_id = i.id 
                          AND r.status = 'issued' 
                          AND u.warehouse_id = ?
                          AND r.created_at BETWEEN ? AND ?) as received_in_period,
                       (SELECT MAX(r.created_at)
                        FROM inv_request_items ri 
                        JOIN inv_requests r ON ri.request_id = r.id 
                        JOIN users u ON r.requester_id = u.id
                        WHERE ri.item_id = i.id AND r.status = 'issued' AND u.warehouse_id = ?) as last_receipt_date
                FROM inv_items i
                ORDER BY i.name ASC";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([$warehouse, $fromDateTime, $toDateTime, $warehouse]);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_items as $item) {
            if ($item['received_in_period'] > 0) {
                $item['current_balance'] = $item['received_in_period'];
                $results[] = $item;
            }
        }
}

// Generate Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Inventory_Report_' . $warehouse . '_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

// Get warehouse name from DB
$wName = $warehouse;
try {
    $stmt = $db->prepare("SELECT name FROM inv_warehouses WHERE id = ?");
    $stmt->execute([$warehouse]);
    if ($w = $stmt->fetch()) {
        $wName = $w['name'];
    }
} catch (Exception $e) {}

echo "<table border='1'>";
echo "<tr><th colspan='6' style='background:#f4f4f4;font-size:16px;'>تقرير المخزون: $wName (من $fromDate إلى $toDate)</th></tr>";
echo "<tr>
        <th style='background:#ddd;'>رقم الصنف</th>
        <th style='background:#ddd;'>اسم الصنف</th>
        <th style='background:#ddd;'>الوحدة</th>
        <th style='background:#ddd;'>" . ($warehouse == 'main' ? 'المورد بالفترة' : 'المستلم بالفترة') . "</th>
        <th style='background:#ddd;'>المتبقي (الرصيد)</th>
        <th style='background:#ddd;'>تاريخ آخر حركة</th>
      </tr>";

foreach ($results as $row) {
    $lastDate = $row['last_receipt_date'] ? date('Y-m-d H:i', strtotime($row['last_receipt_date'])) : '-';
    echo "<tr>
            <td>{$row['item_number']}</td>
            <td>{$row['name']}</td>
            <td>{$row['unit']}</td>
            <td>{$row['received_in_period']}</td>
            <td>{$row['current_balance']}</td>
            <td>{$lastDate}</td>
          </tr>";
}

echo "</table>";
exit;
