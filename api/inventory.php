<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(); // Base auth

$action = $_GET['action'] ?? '';
$user = getCurrentUser();

switch ($action) {
    case 'get_items':
        getItems();
        break;
    case 'get_purchases':
        getPurchases();
        break;
    case 'add_item':
        addItem();
        break;
    case 'update_item':
        updateItem();
        break;
    case 'add_purchase':
        addPurchase();
        break;
    case 'get_requests':
        getRequests();
        break;
    case 'create_request':
        createRequest();
        break;
    case 'update_request_status':
        updateRequestStatus();
        break;
    case 'adjust_sub_stock':
        adjustSubStock();
        break;
    default:
        jsonResponse(false, null, 'Invalid action', 400);
}

function getItems() {
    $db = getDB();
    $items = $db->query("SELECT * FROM inv_items ORDER BY name ASC")->fetchAll();
    jsonResponse(true, $items);
}

function addItem() {
    global $user;
    // Only Admin or Stock Manager (we'll check role later or use permission system)
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $unit = trim($input['unit'] ?? '');
    $itemNumber = trim($input['item_number'] ?? '');

    if (!$name || !$unit) jsonResponse(false, null, 'Name and unit are required', 400);

    try {
        // Check if is_active column exists, add it if not (for schema migration)
        try {
            $db->query("SELECT is_active FROM inv_items LIMIT 1");
        } catch (PDOException $e) {
            $db->exec("ALTER TABLE inv_items ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }

        $stmt = $db->prepare("INSERT INTO inv_items (item_number, name, unit, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$itemNumber, $name, $unit]);
        jsonResponse(true, null, 'Item added successfully');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonResponse(false, null, 'Item number already exists', 400);
        }
        jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
    }
}

function updateItem() {
    global $user;
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $unit = trim($input['unit'] ?? '');
    $itemNumber = trim($input['item_number'] ?? '');
    $isActive = (int)($input['is_active'] ?? 1);

    if (!$id || !$name || !$unit) jsonResponse(false, null, 'Name and unit are required', 400);

    try {
        // Ensure is_active column exists
        try {
            $db->query("SELECT is_active FROM inv_items LIMIT 1");
        } catch (PDOException $e) {
            $db->exec("ALTER TABLE inv_items ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }

        $stmt = $db->prepare("UPDATE inv_items SET item_number = ?, name = ?, unit = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$itemNumber, $name, $unit, $isActive, $id]);
        jsonResponse(true, null, 'تم التعديل بنجاح');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonResponse(false, null, 'رقم الصنف موجود مسبقاً', 400);
        }
        jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
    }
}

function addPurchase() {
    global $user;
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $itemId = (int)($input['item_id'] ?? 0);
    $qty = (float)($input['quantity'] ?? 0);
    $price = (float)($input['price'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    $supplierName = trim($input['supplier_name'] ?? '');
    $invoiceNumber = trim($input['invoice_number'] ?? '');

    if (!$itemId || $qty <= 0) jsonResponse(false, null, 'Invalid data', 400);

    $db->beginTransaction();
    try {
        // Ensure new columns exist
        try {
            $db->query("SELECT supplier_name, invoice_number FROM inv_purchases LIMIT 1");
        } catch (PDOException $e) {
            $db->exec("ALTER TABLE inv_purchases ADD COLUMN supplier_name VARCHAR(255) NULL, ADD COLUMN invoice_number VARCHAR(100) NULL");
        }

        // Insert purchase
        $stmt = $db->prepare("INSERT INTO inv_purchases (item_id, quantity, price, notes, supplier_name, invoice_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$itemId, $qty, $price, $notes, $supplierName, $invoiceNumber, $user['id']]);

        // Update current stock
        $db->prepare("UPDATE inv_items SET current_stock = current_stock + ? WHERE id = ?")->execute([$qty, $itemId]);

        $db->commit();
        jsonResponse(true, null, 'Purchase recorded and stock updated');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
    }
}

function getPurchases() {
    $db = getDB();
    
    // Check schema gracefully
    $columns = 'p.id, p.item_id, p.quantity, p.price, p.notes, p.created_at';
    try {
        $db->query("SELECT supplier_name, invoice_number FROM inv_purchases LIMIT 1");
        $columns .= ', p.supplier_name, p.invoice_number';
    } catch (PDOException $e) {
        // Columns don't exist yet, it's fine, we will just not select them.
    }

    $sql = "SELECT $columns, i.name as item_name, i.unit, u.name as added_by_name 
            FROM inv_purchases p 
            JOIN inv_items i ON p.item_id = i.id 
            LEFT JOIN users u ON p.created_by = u.id 
            ORDER BY p.created_at DESC 
            LIMIT 200";
            
    $purchases = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, $purchases);
}

function getRequests() {
    global $user;
    $db = getDB();
    
    $status = $_GET['status'] ?? '';
    $sql = "SELECT r.*, u.name as requester_name FROM inv_requests r JOIN users u ON r.requester_id = u.id";
    $params = [];

    // Filter by role (requester sees only their own, coordinator/manager see all)
    // For now, let's assume 'waiter', 'chef', 'juice_bar' are requesters
    $requesterRoles = ['waiter', 'chef', 'juice_bar', 'waiter_juice'];
    if (in_array($user['role'], $requesterRoles)) {
        $sql .= " WHERE r.requester_id = ?";
        $params[] = $user['id'];
    }

    if ($status) {
        $sql .= (strpos($sql, 'WHERE') !== false ? " AND" : " WHERE") . " r.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY r.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Fetch items for each request
    foreach ($requests as &$r) {
        $iStmt = $db->prepare("SELECT ri.*, i.name as item_name, i.unit FROM inv_request_items ri JOIN inv_items i ON ri.item_id = i.id WHERE ri.request_id = ?");
        $iStmt->execute([$r['id']]);
        $r['items'] = $iStmt->fetchAll();
    }

    jsonResponse(true, $requests);
}

function createRequest() {
    global $user;
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');

    if (empty($items)) jsonResponse(false, null, 'No items requested', 400);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO inv_requests (requester_id, notes) VALUES (?, ?)");
        $stmt->execute([$user['id'], $notes]);
        $requestId = $db->lastInsertId();

        $iStmt = $db->prepare("INSERT INTO inv_request_items (request_id, item_id, requested_qty) VALUES (?, ?, ?)");
        foreach ($items as $item) {
            $iStmt->execute([$requestId, $item['item_id'], $item['quantity']]);
        }

        $db->commit();
        // Notify Coordinator (implement SSE push here)
        pushEvent('new_inventory_request', ['request_id' => $requestId, 'requester' => $user['name']]);
        
        jsonResponse(true, ['request_id' => $requestId], 'Request created successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
    }
}

function updateRequestStatus() {
    global $user;
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $requestId = (int)($input['request_id'] ?? 0);
    $newStatus = $input['status'] ?? '';
    $reason = $input['reason'] ?? '';
    $items = $input['items'] ?? []; // For quantity adjustments

    if (!$requestId || !$newStatus) jsonResponse(false, null, 'Invalid data', 400);

    // Ensure columns exist BEFORE starting transaction (ALTER TABLE causes implicit commit in MySQL)
    try { $db->query("SELECT updated_at FROM inv_requests LIMIT 1"); } 
    catch (PDOException $e) { $db->exec("ALTER TABLE inv_requests ADD COLUMN updated_at DATETIME NULL"); }
    
    try { $db->query("SELECT received_at FROM inv_requests LIMIT 1"); } 
    catch (PDOException $e) { $db->exec("ALTER TABLE inv_requests ADD COLUMN received_at DATETIME NULL"); }

    // Ensure status enum includes 'received' value
    try {
        $db->exec("ALTER TABLE inv_requests MODIFY COLUMN status ENUM('pending', 'approved', 'issued', 'received', 'rejected', 'cancelled') DEFAULT 'pending'");
    } catch (PDOException $e) {}

    $db->beginTransaction();
    try {
        $sql = "UPDATE inv_requests SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$newStatus];

        if ($newStatus == 'received') {
            $sql .= ", received_at = CURRENT_TIMESTAMP";
        }

        if ($newStatus == 'approved' || $newStatus == 'rejected') {
            $sql .= ", coordinator_id = ?";
            $params[] = $user['id'];
            if ($newStatus == 'rejected') {
                $sql .= ", rejection_reason = ?";
                $params[] = $reason;
            }
        } elseif ($newStatus == 'issued') {
            $sql .= ", warehouse_manager_id = ?";
            $params[] = $user['id'];
        }

        $sql .= " WHERE id = ?";
        $params[] = $requestId;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Get requester warehouse_id to determine sub-warehouse
        $reqStmt = $db->prepare("SELECT u.warehouse_id FROM inv_requests r JOIN users u ON r.requester_id = u.id WHERE r.id = ?");
        $reqStmt->execute([$requestId]);
        $reqInfo = $reqStmt->fetch();
        $warehouseName = 'other';
        if ($reqInfo && !empty($reqInfo['warehouse_id'])) {
            $warehouseName = $reqInfo['warehouse_id'];
        }

        // Handle quantity updates if provided
        if (!empty($items)) {
            $iStmt = $db->prepare("UPDATE inv_request_items SET approved_qty = ?, issued_qty = ?, status = ?, rejection_reason = ? WHERE request_id = ? AND item_id = ?");
            
            // Pre-fetch all requested items to validate
            $reqItemsStmt = $db->prepare("SELECT item_id, requested_qty, approved_qty FROM inv_request_items WHERE request_id = ?");
            $reqItemsStmt->execute([$requestId]);
            $dbItems = [];
            foreach ($reqItemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dbItems[$row['item_id']] = $row;
            }

            foreach ($items as $item) {
                $itemId = $item['item_id'];
                $dbItem = $dbItems[$itemId] ?? ['requested_qty' => 0, 'approved_qty' => 0];
                
                $requestedQty = (float)$dbItem['requested_qty'];
                
                // If status is 'issued', we use the DB's approved_qty to prevent tampering
                $appQty = ($newStatus == 'issued') ? (float)$dbItem['approved_qty'] : (float)($item['approved_qty'] ?? 0);
                $issQty = (float)($item['issued_qty'] ?? 0);
                
                $itemStatus = $item['status'] ?? 'pending';
                $itemReason = $item['rejection_reason'] ?? null;
                
                if ($newStatus === 'rejected' || $newStatus === 'cancelled') {
                    $itemStatus = $newStatus;
                }

                if ($newStatus == 'approved' && $itemStatus !== 'cancelled' && $appQty > $requestedQty) {
                    throw new Exception("الكمية المعتمدة لا يمكن أن تتجاوز الكمية المطلوبة للصنف.");
                }
                if ($newStatus == 'issued' && $itemStatus !== 'cancelled' && $issQty > $appQty) {
                    throw new Exception("الكمية المنصرفة لا يمكن أن تتجاوز الكمية المعتمدة للصنف.");
                }
                
                // If issuing, ensure we have enough stock in the main warehouse
                if ($newStatus == 'issued' && $itemStatus !== 'cancelled' && $issQty > 0) {
                    $stockStmt = $db->prepare("SELECT name, current_stock FROM inv_items WHERE id = ?");
                    $stockStmt->execute([$itemId]);
                    $itemData = $stockStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$itemData) {
                        throw new Exception("الصنف غير موجود في المخزن الرئيسي.");
                    }
                    
                    if ((float)$itemData['current_stock'] < $issQty) {
                        throw new Exception("الرصيد الحالي للصنف ({$itemData['name']}) غير كافٍ للصرف. المتوفر: " . (float)$itemData['current_stock']);
                    }
                }

                $iStmt->execute([$appQty, $issQty, $itemStatus, $itemReason, $requestId, $itemId]);
                
                // If issued, deduct from main stock
                if ($newStatus == 'issued' && isset($item['issued_qty']) && $item['issued_qty'] > 0) {
                    // Deduct from main
                    $db->prepare("UPDATE inv_items SET current_stock = current_stock - ? WHERE id = ?")->execute([$item['issued_qty'], $item['item_id']]);
                }
            }
        }
        
        // If status is 'received', add to sub-warehouse stock
        if ($newStatus == 'received') {
            // Add to sub_warehouse
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

            // Create inv_sub_stock_log if not exists
            try {
                $db->query("SELECT 1 FROM inv_sub_stock_log LIMIT 1");
            } catch (PDOException $e) {
                $db->exec("CREATE TABLE inv_sub_stock_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    warehouse_name VARCHAR(50),
                    item_id INT,
                    action_type ENUM('receive', 'adjust') DEFAULT 'adjust',
                    qty_before DECIMAL(10,2),
                    qty_change DECIMAL(10,2),
                    qty_after DECIMAL(10,2),
                    note VARCHAR(255),
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
            
            $reqItemsStmt = $db->prepare("SELECT item_id, issued_qty FROM inv_request_items WHERE request_id = ? AND status != 'cancelled'");
            $reqItemsStmt->execute([$requestId]);
            
            $subStmt = $db->prepare("SELECT id, current_balance FROM inv_sub_stock WHERE warehouse_name = ? AND item_id = ?");
            $updSubStmt = $db->prepare("UPDATE inv_sub_stock SET current_balance = current_balance + ?, last_received_at = CURRENT_TIMESTAMP WHERE warehouse_name = ? AND item_id = ?");
            $insSubStmt = $db->prepare("INSERT INTO inv_sub_stock (warehouse_name, item_id, current_balance, last_received_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");

            foreach ($reqItemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $issQty = (float)$row['issued_qty'];
                if ($issQty > 0) {
                    $subStmt->execute([$warehouseName, $row['item_id']]);
                    $subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
                    $qtyBefore = $subRow ? (float)$subRow['current_balance'] : 0;
                    $qtyAfter = $qtyBefore + $issQty;

                    if ($subRow) {
                        $updSubStmt->execute([$issQty, $warehouseName, $row['item_id']]);
                    } else {
                        $insSubStmt->execute([$warehouseName, $row['item_id'], $issQty]);
                    }

                    // Log receipt
                    $db->prepare("INSERT INTO inv_sub_stock_log (warehouse_name, item_id, action_type, qty_before, qty_change, qty_after, note, created_by) VALUES (?, ?, 'receive', ?, ?, ?, ?, ?)")
                       ->execute([$warehouseName, $row['item_id'], $qtyBefore, $issQty, $qtyAfter, "استلام طلب صرف رقم $requestId", $user['id']]);
                }
            }
        }

        $db->commit();
        
        // SSE Notifications
        pushEvent('inventory_request_status_updated', [
            'request_id' => $requestId, 
            'status' => $newStatus,
            'message' => $newStatus == 'rejected' ? 'تم رفض طلبك: ' . $reason : 'تم تحديث حالة طلبك'
        ]);

        jsonResponse(true, null, 'Status updated successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
    }
}

function adjustSubStock() {
    global $user;
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $warehouse = trim($input['warehouse'] ?? '');
    $itemId = (int)($input['item_id'] ?? 0);
    $newBalance = (float)($input['new_balance'] ?? 0);
    $notes = trim($input['notes'] ?? '');

    if (empty($warehouse) || !$itemId) {
        jsonResponse(false, null, 'Invalid data', 400);
    }

    try {
        // Ensure table exists
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

        // Also ensure inv_sub_stock_log exists
        try {
            $db->query("SELECT 1 FROM inv_sub_stock_log LIMIT 1");
        } catch (PDOException $e) {
            $db->exec("CREATE TABLE inv_sub_stock_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                warehouse_name VARCHAR(50),
                item_id INT,
                action_type ENUM('receive', 'adjust') DEFAULT 'adjust',
                qty_before DECIMAL(10,2),
                qty_change DECIMAL(10,2),
                qty_after DECIMAL(10,2),
                note VARCHAR(255),
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }

        $db->beginTransaction();

        // Get current balance
        $stmt = $db->prepare("SELECT current_balance FROM inv_sub_stock WHERE warehouse_name = ? AND item_id = ?");
        $stmt->execute([$warehouse, $itemId]);
        $row = $stmt->fetch();
        $qtyBefore = $row ? (float)$row['current_balance'] : 0;
        
        $qtyChange = $newBalance - $qtyBefore;

        // Update balance
        if ($row) {
            $db->prepare("UPDATE inv_sub_stock SET current_balance = ?, last_received_at = CURRENT_TIMESTAMP WHERE warehouse_name = ? AND item_id = ?")
               ->execute([$newBalance, $warehouse, $itemId]);
        } else {
            $db->prepare("INSERT INTO inv_sub_stock (warehouse_name, item_id, current_balance, last_received_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)")
               ->execute([$warehouse, $itemId, $newBalance]);
        }

        // Log adjustment
        $db->prepare("INSERT INTO inv_sub_stock_log (warehouse_name, item_id, action_type, qty_before, qty_change, qty_after, note, created_by) VALUES (?, ?, 'adjust', ?, ?, ?, ?, ?)")
           ->execute([$warehouse, $itemId, $qtyBefore, $qtyChange, $newBalance, $notes ?: 'تسوية يدوية', $user['id']]);

        $db->commit();
        jsonResponse(true, null, 'تم تسوية رصيد المخزن الفرعي بنجاح');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
    }
}
