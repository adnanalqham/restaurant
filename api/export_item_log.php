<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(); // Base auth

$item_id = (int)($_GET['item_id'] ?? 0);
$warehouse = $_GET['warehouse'] ?? 'main';
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');

if (!$item_id) {
    die("Invalid Item ID");
}

$fromDateTime = $fromDate . " 00:00:00";
$toDateTime = $toDate . " 23:59:59";

$db = getDB();

// Get item name and unit
$stmt = $db->prepare("SELECT name, unit FROM inv_items WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();
if (!$item) die("Item not found");
$itemName = $item['name'];
$unit = $item['unit'];

// Get warehouse name
$wName = $warehouse;
if ($warehouse !== 'main') {
    $wStmt = $db->prepare("SELECT name FROM inv_warehouses WHERE id = ?");
    $wStmt->execute([$warehouse]);
    if ($w = $wStmt->fetch()) {
        $wName = $w['name'];
    }
} else {
    $wName = 'المخزن الرئيسي';
}

$logs = [];

try {
    if ($warehouse === 'main') {
        // Purchases (Additions)
        $stmt1 = $db->prepare("SELECT quantity as qty, 'addition' as type, created_at as date, supplier_name as details, invoice_number as ref FROM inv_purchases WHERE item_id = ? AND created_at BETWEEN ? AND ?");
        $stmt1->execute([$item_id, $fromDateTime, $toDateTime]);
        $additions = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        foreach ($additions as &$a) { $a['label'] = 'توريد (مشتريات)'; $logs[] = $a; }

        // Issues (Deductions)
        $stmt2 = $db->prepare("SELECT ri.issued_qty as qty, 'deduction' as type, r.created_at as date, u.warehouse_id as details, r.id as ref 
                               FROM inv_request_items ri 
                               JOIN inv_requests r ON ri.request_id = r.id 
                               JOIN users u ON r.requester_id = u.id 
                               WHERE ri.item_id = ? AND r.status = 'issued' AND r.created_at BETWEEN ? AND ?");
        $stmt2->execute([$item_id, $fromDateTime, $toDateTime]);
        $deductions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $whMap = ['kitchen'=>'المطبخ','bar'=>'البار','shisha'=>'الشيشة / عصائر','hall'=>'الصالة'];
        foreach ($deductions as &$d) { 
            $d['label'] = 'صرف'; 
            $d['details'] = 'إلى: ' . ($whMap[$d['details']] ?? $d['details']); 
            $logs[] = $d; 
        }
    } else {
        // Sub-warehouse receipts
        $stmt = $db->prepare("SELECT ri.issued_qty as qty, 'addition' as type, r.created_at as date, 'من المخزن الرئيسي' as details, r.id as ref 
                               FROM inv_request_items ri 
                               JOIN inv_requests r ON ri.request_id = r.id 
                               JOIN users u ON r.requester_id = u.id 
                               WHERE ri.item_id = ? AND r.status = 'issued' AND u.warehouse_id = ? AND r.created_at BETWEEN ? AND ?");
        $stmt->execute([$item_id, $warehouse, $fromDateTime, $toDateTime]);
        $additions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($additions as &$a) { $a['label'] = 'استلام'; $logs[] = $a; }
    }

    usort($logs, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

} catch (PDOException $e) {
    die("DB Error");
}

// Generate Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Item_Log_' . $itemName . '_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

echo "<table border='1'>";
echo "<tr><th colspan='4' style='background:#f4f4f4;font-size:16px;'>سجل حركات الصنف: $itemName ($wName) - من $fromDate إلى $toDate</th></tr>";
echo "<tr>
        <th style='background:#ddd;'>التاريخ والوقت</th>
        <th style='background:#ddd;'>نوع العملية</th>
        <th style='background:#ddd;'>الكمية ($unit)</th>
        <th style='background:#ddd;'>التفاصيل</th>
      </tr>";

if (empty($logs)) {
    echo "<tr><td colspan='4' style='text-align:center;'>لا توجد بيانات</td></tr>";
} else {
    foreach ($logs as $row) {
        $qtyStr = ($row['type'] === 'addition' ? '+' : '-') . $row['qty'];
        $detailsStr = $row['details'] . ($row['ref'] ? ' (مرجع: ' . $row['ref'] . ')' : '');
        echo "<tr>
                <td>{$row['date']}</td>
                <td>{$row['label']}</td>
                <td style='direction:ltr; text-align:right;'>{$qtyStr}</td>
                <td>{$detailsStr}</td>
              </tr>";
    }
}

echo "</table>";
exit;
