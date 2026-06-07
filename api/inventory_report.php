<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(); // Base auth

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_warehouses':
        getWarehouses();
        break;
    case 'get_warehouse_stock':
        getWarehouseStock();
        break;
    case 'get_item_log':
        getItemLog();
        break;
    default:
        jsonResponse(false, null, 'Invalid action', 400);
}

function getWarehouses() {
    $db = getDB();
    try {
        $db->query("SELECT 1 FROM inv_warehouses LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE inv_warehouses (
            id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("INSERT INTO inv_warehouses (id, name) VALUES 
            ('main', 'المخزن الرئيسي'),
            ('kitchen', 'المطبخ'),
            ('bar', 'البار'),
            ('shisha', 'الشيشة / عصائر'),
            ('hall', 'الصالة')
        ");
    }
    
    $warehouses = $db->query("SELECT id, name FROM inv_warehouses ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $warehouses);
}

function getWarehouseStock() {
    $db = getDB();
    
    $warehouse = $_GET['warehouse'] ?? 'main';
    $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $toDate = $_GET['to_date'] ?? date('Y-m-d');
    
    $fromDateTime = $fromDate . " 00:00:00";
    $toDateTime = $toDate . " 23:59:59";

    $results = [];

    try {
        if ($warehouse === 'main') {
            $sql = "SELECT i.id, i.name, i.unit, i.current_stock as current_balance, i.item_number,
                           (SELECT COALESCE(SUM(quantity), 0) FROM inv_purchases p WHERE p.item_id = i.id AND p.created_at BETWEEN ? AND ?) as received_in_period,
                           (SELECT MAX(created_at) FROM inv_purchases p WHERE p.item_id = i.id) as last_receipt_date
                    FROM inv_items i 
                    ORDER BY i.name ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$fromDateTime, $toDateTime]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Join with inv_sub_stock to get actual remaining balance
            try {
                $db->query("SELECT 1 FROM inv_sub_stock LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("CREATE TABLE inv_sub_stock (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    warehouse_name VARCHAR(50),
                    item_id INT,
                    current_balance DECIMAL(10,2) DEFAULT 0,
                    last_received_at DATETIME
                )");
            }

            $sql = "SELECT i.id, i.name, i.unit, i.item_number,
                           COALESCE(ss.current_balance, 0) as current_balance,
                           (SELECT COALESCE(SUM(ri.issued_qty), 0) 
                            FROM inv_request_items ri 
                            JOIN inv_requests r ON ri.request_id = r.id 
                            JOIN users u ON r.requester_id = u.id
                            WHERE ri.item_id = i.id 
                              AND (r.status = 'issued' OR r.status = 'received') 
                              AND u.warehouse_id = ?
                              AND r.created_at BETWEEN ? AND ?) as received_in_period,
                           (SELECT MAX(r.created_at)
                            FROM inv_request_items ri 
                            JOIN inv_requests r ON ri.request_id = r.id 
                            JOIN users u ON r.requester_id = u.id
                            WHERE ri.item_id = i.id AND (r.status = 'issued' OR r.status = 'received') AND u.warehouse_id = ?) as last_receipt_date
                    FROM inv_items i
                    LEFT JOIN inv_sub_stock ss ON ss.item_id = i.id AND ss.warehouse_name = ?
                    ORDER BY i.name ASC";
                    
            $stmt = $db->prepare($sql);
            $stmt->execute([$warehouse, $fromDateTime, $toDateTime, $warehouse, $warehouse]);
            $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_items as $item) {
                if ($item['received_in_period'] > 0 || $item['current_balance'] > 0) {
                    $results[] = $item;
                }
            }
        }
        jsonResponse(true, $results);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'DB Error: ' . $e->getMessage(), 500);
    }
}

function getItemLog() {
    $db = getDB();
    $item_id = (int)($_GET['item_id'] ?? 0);
    $warehouse = $_GET['warehouse'] ?? 'main';
    $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $toDate = $_GET['to_date'] ?? date('Y-m-d');
    
    $fromDateTime = $fromDate . " 00:00:00";
    $toDateTime = $toDate . " 23:59:59";

    if (!$item_id) {
        jsonResponse(false, null, 'Invalid item ID', 400);
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
            
            // Map warehouse IDs to names
            $whMap = ['kitchen'=>'المطبخ','bar'=>'البار','shisha'=>'الشيشة / عصائر','hall'=>'الصالة'];
            foreach ($deductions as &$d) { 
                $d['label'] = 'صرف'; 
                $d['details'] = 'إلى: ' . ($whMap[$d['details']] ?? $d['details']); 
                $logs[] = $d; 
            }
        } else {
            // Try to fetch from inv_sub_stock_log first
            $hasLogTable = false;
            try {
                $db->query("SELECT 1 FROM inv_sub_stock_log LIMIT 1");
                $hasLogTable = true;
            } catch (PDOException $e) {}

            if ($hasLogTable) {
                $stmt = $db->prepare("SELECT 
                                        sl.qty_change as qty,
                                        IF(sl.action_type = 'receive', 'addition', IF(sl.qty_change >= 0, 'addition', 'deduction')) as type,
                                        sl.created_at as date,
                                        sl.note as details,
                                        sl.action_type,
                                        sl.id as ref,
                                        u.name as user_name
                                      FROM inv_sub_stock_log sl
                                      LEFT JOIN users u ON sl.created_by = u.id
                                      WHERE sl.warehouse_name = ? AND sl.item_id = ? AND sl.created_at BETWEEN ? AND ?
                                      ORDER BY sl.created_at DESC");
                $stmt->execute([$warehouse, $item_id, $fromDateTime, $toDateTime]);
                $dbLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($dbLogs as $log) {
                    $logs[] = [
                        'qty' => abs((float)$log['qty']),
                        'type' => $log['type'],
                        'date' => $log['date'],
                        'details' => $log['details'] . ($log['user_name'] ? " (بواسطة: {$log['user_name']})" : ""),
                        'ref' => $log['ref'],
                        'label' => ($log['action_type'] === 'receive' ? 'استلام' : 'تسوية يدوية')
                    ];
                }
            }

            // Also fetch any historic / pending 'issued' request items that were not received via the new system yet
            $stmt = $db->prepare("SELECT ri.issued_qty as qty, 'addition' as type, r.created_at as date, 'طلب صرف من المخزن الرئيسي' as details, r.id as ref 
                                   FROM inv_request_items ri 
                                   JOIN inv_requests r ON ri.request_id = r.id 
                                   JOIN users u ON r.requester_id = u.id 
                                   WHERE ri.item_id = ? AND r.status = 'issued' AND u.warehouse_id = ? AND r.created_at BETWEEN ? AND ?");
            $stmt->execute([$item_id, $warehouse, $fromDateTime, $toDateTime]);
            $additions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($additions as &$a) { 
                $a['label'] = 'صرف من الرئيسي'; 
                $logs[] = $a; 
            }
        }

        // Sort logs by date descending
        usort($logs, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        jsonResponse(true, $logs);
    } catch (PDOException $e) {
        jsonResponse(false, null, 'DB Error: ' . $e->getMessage(), 500);
    }
}
