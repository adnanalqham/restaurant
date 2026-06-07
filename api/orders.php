<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/print_helper.php';
require_once __DIR__ . '/print_direct_lib.php';
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        $action === 'single' ? getSingleOrder() : getOrders();
        break;
    case 'POST':
        switch ($action) {
            case 'update_status':
                updateOrderStatus();
                break;
            case 'update_item_status':
                updateItemStatus();
                break;
            case 'update_order_items_status':
                updateOrderItemsStatus();
                break;
            case 'append_to_order':
                appendToOrder();
                break;
            case 'delete_item':
                deleteItemFromOrder();
                break;
            case 'apply_discount':
                applyManualDiscount();
                break;
            case 'update_payment_note':
                updatePaymentNote();
                break;
            case 'delete':
                deleteOrder();
                break;
            case 'deliver_all_active':
                deliverAllActiveOrders();
                break;
            default:
                createOrder();
                break;
        }
        break;
    case 'DELETE':
        deleteOrder();
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getOrders()
{
    session_write_close();
    $db = getDB();
    $user = getCurrentUser();
    $role = $user['role'];

    $sql = "SELECT o.*,
                   w.name as waiter_name,
                   c.name as cashier_name,
                   o.direct_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users w ON o.waiter_id = w.id
            LEFT JOIN users c ON o.cashier_id = c.id
            WHERE 1=1";
    $params = [];

    // Check if user is a kitchen role
    $isKitchen = in_array($role, ['chef', 'juice_bar', 'kitchen']);
    $allowedCategories = [];
    if ($isKitchen) {
        $pStmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id=?");
        $pStmt->execute([$user['id']]);
        $allowedCategories = array_column($pStmt->fetchAll(), 'category_id');
        
        if (!empty($allowedCategories)) {
            $catMarks = implode(',', array_fill(0, count($allowedCategories), '?'));
            $sql .= " AND EXISTS (
                SELECT 1 FROM order_items oi2 
                WHERE oi2.order_id = o.id 
                AND oi2.category_id IN ($catMarks)
            )";
            $params = array_merge($params, $allowedCategories);
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $date = $_GET['date'] ?? '';

    if ($status === 'active') {
        if ($role === 'cashier') {
            $sql .= " AND o.status NOT IN ('cancelled', 'pending') AND (o.paid_at IS NULL OR o.delivered_at IS NULL)";
        } elseif ($isKitchen) {
            if (!empty($allowedCategories)) {
                $statusCatMarks = implode(',', array_fill(0, count($allowedCategories), '?'));
                $sql .= " AND o.status NOT IN ('cancelled', 'pending', 'delivered') AND EXISTS (
                            SELECT 1 FROM order_items oi_stat
                            WHERE oi_stat.order_id=o.id 
                            AND oi_stat.category_id IN ($statusCatMarks)
                            AND oi_stat.status NOT IN ('ready','served','rejected')
                          )";
                $params = array_merge($params, $allowedCategories);
            } else {
                $sql .= " AND o.status NOT IN ('cancelled', 'pending', 'delivered') AND EXISTS (
                            SELECT 1 FROM order_items oi_stat
                            WHERE oi_stat.order_id=o.id 
                            AND oi_stat.status NOT IN ('ready','served','rejected')
                          )";
            }
        } else {
            $sql .= " AND o.status NOT IN ('cancelled', 'pending', 'delivered')";
        }
    } elseif ($status === 'ready') {
        if ($isKitchen) {
            if (!empty($allowedCategories)) {
                $statusCatMarks = implode(',', array_fill(0, count($allowedCategories), '?'));
                $sql .= " AND o.status NOT IN ('cancelled', 'delivered') AND EXISTS (
                            SELECT 1 FROM order_items oi_stat
                            WHERE oi_stat.order_id=o.id 
                            AND oi_stat.category_id IN ($statusCatMarks)
                            AND oi_stat.status IN ('ready','served')
                          )";
                $params = array_merge($params, $allowedCategories);
            } else {
                $sql .= " AND o.status NOT IN ('cancelled', 'delivered') AND EXISTS (
                            SELECT 1 FROM order_items oi_stat
                            WHERE oi_stat.order_id=o.id 
                            AND oi_stat.status IN ('ready','served')
                          )";
            }
        } else {
            $sql .= " AND (o.status='ready' OR (o.status IN ('confirmed','paid') AND NOT EXISTS (
                        SELECT 1 FROM order_items 
                        WHERE order_id=o.id AND status NOT IN ('ready','served')
                     )))";
        }
    } elseif ($status === 'in_progress') {
        $sql .= " AND (o.status='in_progress' OR (o.status IN ('confirmed','paid') AND EXISTS (
                    SELECT 1 FROM order_items 
                    WHERE order_id=o.id AND status='in_progress'
                 )))";
    } elseif ($status === 'paid') {
        $sql .= " AND (o.status='paid' OR o.paid_at IS NOT NULL)";
    } elseif ($status === 'delivered') {
        $sql .= " AND (o.status='delivered' OR o.delivered_at IS NOT NULL)";
    } elseif ($status) {
        $sql .= " AND o.status=?";
        $params[] = $status;
    }

    if ($date) {
        $sql .= " AND DATE(o.created_at)=?";
        $params[] = $date;
    }

    if (!empty($_GET["from_date"])) {
        $sql .= " AND DATE(o.created_at) >= ?";
        $params[] = $_GET["from_date"];
    }

    if (!empty($_GET["to_date"])) {
        $sql .= " AND DATE(o.created_at) <= ?";
        $params[] = $_GET["to_date"];
    }

    $search = $_GET['search'] ?? '';
    if ($search) {
        $sql .= " AND (o.order_number LIKE ? OR o.table_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Waiter sees only their own orders
    if ($role === 'waiter') {
        $sql .= " AND o.waiter_id=?";
        $params[] = $user['id'];
    }

    // Cashier sees only orders assigned to them
    if ($role === 'cashier') {
        $sql .= " AND o.cashier_id=?";
        $params[] = $user['id'];
    }

    $sql .= " ORDER BY o.created_at DESC";

    // Pagination
    $limit = (int) ($_GET['limit'] ?? 50);
    $offset = (int) ($_GET['offset'] ?? 0);
    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Attach items to each order
    foreach ($orders as &$order) {
        $itemSql = "
            SELECT oi.*, c.name_ar as cat_name_ar, i.item_number 
            FROM order_items oi 
            JOIN categories c ON oi.category_id = c.id 
            LEFT JOIN items i ON oi.item_id = i.id
            WHERE oi.order_id = ?
        ";
        $itemParams = [$order['id']];
        
        if ($isKitchen && !empty($allowedCategories)) {
            $catMarksItems = implode(',', array_fill(0, count($allowedCategories), '?'));
            $itemSql .= " AND oi.category_id IN ($catMarksItems)";
            $itemParams = array_merge($itemParams, $allowedCategories);
        }
        
        $iStmt = $db->prepare($itemSql);
        $iStmt->execute($itemParams);
        $order['items'] = $iStmt->fetchAll();
    }

    jsonResponse(true, $orders);
}

function getSingleOrder()
{
    session_write_close();
    $db = getDB();
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id)
        jsonResponse(false, null, 'معرف غير صالح', 400);

    $stmt = $db->prepare("
        SELECT o.*, 
               w.name as waiter_name, 
               c.name as cashier_name 
        FROM orders o 
        LEFT JOIN users w ON o.waiter_id=w.id 
        LEFT JOIN users c ON o.cashier_id=c.id 
        WHERE o.id=?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order)
        jsonResponse(false, null, 'الطلب غير موجود', 404);

    $iStmt = $db->prepare("
        SELECT oi.*, c.name_ar as cat_name_ar, c.name_en as cat_name_en, i.item_number 
        FROM order_items oi 
        JOIN categories c ON oi.category_id = c.id 
        LEFT JOIN items i ON oi.item_id = i.id
        WHERE oi.order_id = ?
    ");
    $iStmt->execute([$id]);
    $order['items'] = $iStmt->fetchAll();

    jsonResponse(true, $order);
}

function createOrder()
{
    $db = getDB();
    $user = getCurrentUser();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input && !empty($_POST))
        $input = $_POST;

    $tableNum = trim($input['table_number'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $items = $input['items'] ?? [];
    $payMethod = in_array($input['payment_method'] ?? '', ['cash', 'card', 'wallet']) ? $input['payment_method'] : 'cash';
    $walletId = !empty($input['wallet_id']) ? (int) $input['wallet_id'] : null;
    $walletName = trim($input['wallet_name'] ?? '');
    $payRef = trim($input['payment_reference'] ?? '');
    $assignedCashierId = !empty($input['assigned_cashier_id']) ? (int) $input['assigned_cashier_id'] : null;

    if (empty($items))
        jsonResponse(false, null, 'لا توجد أصناف في الطلب', 400);
    if (empty($tableNum))
        jsonResponse(false, null, 'يرجى إدخال رقم الطاولة أولاً', 400);
    if ($payMethod === 'wallet' && !$walletId)
        jsonResponse(false, null, 'يرجى اختيار المحفظة', 400);
    if (!$assignedCashierId)
        jsonResponse(false, null, 'يرجى اختيار الكاشير المسؤول عن الطلب', 400);

    $cStmt = $db->prepare("SELECT id FROM users WHERE id=? AND role_id=(SELECT id FROM roles WHERE name='cashier') AND is_active=1");
    $cStmt->execute([$assignedCashierId]);
    if (!$cStmt->fetch())
        jsonResponse(false, null, 'الكاشير المحدد غير صالح', 400);

    $orderNumber = generateOrderNumber();
    $subtotal = 0;

    $settings = getSettings();
    $taxRate = (float) ($settings['tax_rate'] ?? 0);
    $serviceRate = (float) ($settings['service_charge'] ?? 0);

    $stmt = $db->prepare("INSERT INTO orders (order_number, table_number, waiter_id, cashier_id, notes, status, payment_method, wallet_id, wallet_name, payment_reference, subtotal, tax, discount, total) VALUES (?,?,?,?,?,'sent_to_cashier',?,?,?,?,0,0,0,0)");
    $stmt->execute([$orderNumber, $tableNum, $user['id'], $assignedCashierId, $notes, $payMethod, $walletId, $walletName ?: null, $payRef ?: null]);
    $orderId = $db->lastInsertId();

    $iStmt = $db->prepare("INSERT INTO order_items (order_id, item_id, item_name_ar, item_name_en, category_id, quantity, unit_price, subtotal, notes, size_name) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($items as $item) {
        $itemId = (int) ($item['item_id'] ?? 0);
        $isCombo = !empty($item['is_combo']);
        $comboId = (int) ($item['combo_id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 1);
        $itemNote = trim($item['notes'] ?? '');
        $uPrice = (float) ($item['unit_price'] ?? 0);

        if ($isCombo && $comboId) {
            $cStmt = $db->prepare("SELECT * FROM offers WHERE id=?");
            $cStmt->execute([$comboId]);
            $combo = $cStmt->fetch();
            if (!$combo) continue;

            $cPrice = (float)$combo['price'];
            $cNameAr = "[عرض] " . $combo['name_ar'];
            $lineTotal = $cPrice * $qty;
            $subtotal += $lineTotal;

            $iStmt->execute([$orderId, 0, $cNameAr, 'Combo: ' . $combo['name_ar'], 0, $qty, $cPrice, $lineTotal, $itemNote, null]);

            $subStmt = $db->prepare("
                SELECT oi.item_id, oi.quantity as sub_qty, i.name_ar, i.name_en, i.category_id 
                FROM offer_items oi 
                JOIN items i ON oi.item_id = i.id 
                WHERE oi.offer_id = ?
            ");
            $subStmt->execute([$comboId]);
            $components = $subStmt->fetchAll();

            foreach ($components as $cp) {
                $totalSubQty = $cp['sub_qty'] * $qty;
                $iStmt->execute([$orderId, $cp['item_id'], " - " . $cp['name_ar'], " - " . $cp['name_en'], $cp['category_id'], $totalSubQty, 0, 0, "ضمن باقة: " . $combo['name_ar'], null]);
            }
            continue;
        }

        $sizeNameAr = trim($item['size_name'] ?? '');
        $addonsList = $item['addons'] ?? [];

        $iRow = $db->prepare("SELECT * FROM items WHERE id=? AND is_available=1");
        $iRow->execute([$itemId]);
        $itemData = $iRow->fetch();
        if (!$itemData)
            continue;

        $finalPrice = ($uPrice > 0) ? $uPrice : (float) $itemData['price'];
        $finalNameAr = $itemData['name_ar'];
        $finalNameEn = $itemData['name_en'];

        if ($itemData['has_sizes'] == 1 && $sizeNameAr) {
            $sizesArr = json_decode($itemData['sizes'], true);
            if (is_array($sizesArr)) {
                foreach ($sizesArr as $s) {
                    if (trim($s['name_ar']) === $sizeNameAr) {
                        $finalPrice = (float) $s['price'];
                        $finalNameAr .= ' - ' . $s['name_ar'];
                        $finalNameEn .= ' - ' . $s['name_en'];
                        break;
                    }
                }
            }
        }

        if ($itemData['has_addons'] == 1 && !empty($addonsList) && is_array($addonsList)) {
            $addonsArr = json_decode($itemData['addons'], true);
            if (is_array($addonsArr)) {
                $validAddonNamesAr = [];
                $validAddonNamesEn = [];
                foreach ($addonsList as $addonName) {
                    foreach ($addonsArr as $a) {
                        if (trim($a['name_ar']) === trim($addonName)) {
                            $finalPrice += (float) $a['price'];
                            $validAddonNamesAr[] = $a['name_ar'];
                            $validAddonNamesEn[] = $a['name_en'] ?? $a['name_ar'];
                            break;
                        }
                    }
                }
                if (!empty($validAddonNamesAr)) {
                    $finalNameAr .= ' (+ ' . implode(' + ', $validAddonNamesAr) . ')';
                    $finalNameEn .= ' (+ ' . implode(' + ', $validAddonNamesEn) . ')';
                }
            }
        }

        $lineTotal = $finalPrice * $qty;
        $subtotal += $lineTotal;
        try {
            $iStmt->execute([$orderId, $itemId, $finalNameAr, $finalNameEn, $itemData['category_id'], $qty, $finalPrice, $lineTotal, $itemNote, $sizeNameAr ?: null]);
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . ' - Insert Item Error: ' . $e->getMessage() . "\n", FILE_APPEND);
            throw $e;
        }
    }

    $taxAmount = $subtotal * ($taxRate / 100);
    $serviceAmount = $subtotal * ($serviceRate / 100);
    $grandTotal = $subtotal + $taxAmount + $serviceAmount;

    $db->prepare("UPDATE orders SET subtotal=?, tax=?, service_charge=?, total=? WHERE id=?")->execute([$subtotal, $taxAmount, $serviceAmount, $grandTotal, $orderId]);

    pushEvent('new_order', ['order_id' => $orderId, 'order_number' => $orderNumber, 'waiter' => $user['name'], 'table' => $tableNum]);
    logActivity("إنشاء طلب", "طلب رقم $orderNumber للطاولة $tableNum");
    
    // Auto-print to Kitchen (via print server if configured)
    triggerPrint('kitchen', $orderId);

    jsonResponse(true, ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $subtotal], 'تم إنشاء الطلب بنجاح');
}

function appendToOrder()
{
    $db = getDB();
    $user = getCurrentUser();

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int) ($input['order_id'] ?? 0);
    $items = $input['items'] ?? [];

    if (!$orderId) jsonResponse(false, null, 'معرف الطلب غير صحيح', 400);
    if (empty($items)) jsonResponse(false, null, 'لا توجد أصناف للإضافة', 400);

    $oStmt = $db->prepare("SELECT * FROM orders WHERE id=?");
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch();

    if (!$order) jsonResponse(false, null, 'الطلب غير موجود', 404);
    if (in_array($order['status'], ['cancelled', 'delivered', 'paid'])) {
        if (!in_array($user['role'], ['admin', 'cashier'])) {
            jsonResponse(false, null, 'لا يمكن للويتر الإضافة لطلب ملغي أو مسدد أو تم تسليمه', 400);
        }
    }

    $settings = getSettings();
    $taxRate = (float) ($settings['tax_rate'] ?? 0);
    $serviceRate = (float) ($settings['service_charge'] ?? 0);

    $db->beginTransaction();
    try {
        $addedSubtotal = 0;
        $iStmt = $db->prepare("INSERT INTO order_items (order_id, item_id, item_name_ar, item_name_en, category_id, quantity, unit_price, subtotal, notes, size_name, is_appended) VALUES (?,?,?,?,?,?,?,?,?,?,1)");

        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            $isCombo = !empty($item['is_combo']);
            $comboId = (int) ($item['combo_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            $itemNote = trim($item['notes'] ?? '');
            $uPrice = (float) ($item['unit_price'] ?? 0);

            if ($isCombo && $comboId) {
                $cStmt = $db->prepare("SELECT * FROM offers WHERE id=?");
                $cStmt->execute([$comboId]);
                $combo = $cStmt->fetch();
                if (!$combo) continue;

                $cPrice = (float)$combo['price'];
                $cNameAr = "[عرض] " . $combo['name_ar'];
                $lineTotal = $cPrice * $qty;
                $addedSubtotal += $lineTotal;

                $iStmt->execute([$orderId, 0, $cNameAr, 'Combo: ' . $combo['name_ar'], 0, $qty, $cPrice, $lineTotal, $itemNote, null]);

                $subStmt = $db->prepare("
                    SELECT oi.item_id, oi.quantity as sub_qty, i.name_ar, i.name_en, i.category_id 
                    FROM offer_items oi 
                    JOIN items i ON oi.item_id = i.id 
                    WHERE oi.offer_id = ?
                ");
                $subStmt->execute([$comboId]);
                $components = $subStmt->fetchAll();

                foreach ($components as $cp) {
                    $totalSubQty = $cp['sub_qty'] * $qty;
                    $iStmt->execute([$orderId, $cp['item_id'], " - " . $cp['name_ar'], " - " . $cp['name_en'], $cp['category_id'], $totalSubQty, 0, 0, "ضمن باقة: " . $combo['name_ar'], null]);
                }
                continue;
            }

            $itemNote = trim($item['notes'] ?? '');
            $sizeNameAr = trim($item['size_name'] ?? '');
            $addonsList = $item['addons'] ?? [];

            $iRow = $db->prepare("SELECT * FROM items WHERE id=? AND is_available=1");
            $iRow->execute([$itemId]);
            $itemData = $iRow->fetch();
            if (!$itemData) continue;

            $finalPrice = ($uPrice > 0) ? $uPrice : (float) $itemData['price'];
            $finalNameAr = $itemData['name_ar'];
            $finalNameEn = $itemData['name_en'];

            if ($itemData['has_sizes'] == 1 && $sizeNameAr) {
                $sizesArr = json_decode($itemData['sizes'], true);
                if (is_array($sizesArr)) {
                    foreach ($sizesArr as $s) {
                        if (trim($s['name_ar']) === $sizeNameAr) {
                            $finalPrice = (float) $s['price'];
                            $finalNameAr .= ' - ' . $s['name_ar'];
                            $finalNameEn .= ' - ' . $s['name_en'];
                            break;
                        }
                    }
                }
            }

            if ($itemData['has_addons'] == 1 && !empty($addonsList) && is_array($addonsList)) {
                $addonsArr = json_decode($itemData['addons'], true);
                if (is_array($addonsArr)) {
                    $validAddonNamesAr = [];
                    $validAddonNamesEn = [];
                    foreach ($addonsList as $addonName) {
                        foreach ($addonsArr as $a) {
                            if (trim($a['name_ar']) === trim($addonName)) {
                                $finalPrice += (float) $a['price'];
                                $validAddonNamesAr[] = $a['name_ar'];
                                $validAddonNamesEn[] = $a['name_en'] ?? $a['name_ar'];
                                break;
                            }
                        }
                    }
                    if (!empty($validAddonNamesAr)) {
                        $finalNameAr .= ' (+ ' . implode(' + ', $validAddonNamesAr) . ')';
                        $finalNameEn .= ' (+ ' . implode(' + ', $validAddonNamesEn) . ')';
                    }
                }
            }

            $lineTotal = $finalPrice * $qty;
            $addedSubtotal += $lineTotal;
            $iStmt->execute([$orderId, $itemId, $finalNameAr, $finalNameEn, $itemData['category_id'], $qty, $finalPrice, $lineTotal, $itemNote, $sizeNameAr ?: null]);
        }

        $newSubtotal = $order['subtotal'] + $addedSubtotal;
        $taxAmount = $newSubtotal * ($taxRate / 100);
        $serviceAmount = $newSubtotal * ($serviceRate / 100);
        $grandTotal = $newSubtotal + $taxAmount + $serviceAmount - (float)$order['manual_discount'];
        if ($grandTotal < 0) $grandTotal = 0;

        $db->prepare("UPDATE orders SET status='confirmed', ready_at=NULL, paid_at=NULL, subtotal=?, tax=?, service_charge=?, total=? WHERE id=?")->execute([$newSubtotal, $taxAmount, $serviceAmount, $grandTotal, $orderId]);

        $db->commit();
        pushEvent('order_status_changed', ['order_id' => $orderId, 'status' => 'appended']);
        pushEvent('item_status_changed', ['order_id' => $orderId, 'message' => "إضافة أصناف جديدة للطلب #{$order['order_number']}"]);
        logActivity("تعديل طلب", "إضافة أصناف للطلب رقم {$order['order_number']}");
        
        // Auto-print newly added items to Kitchen
        triggerPrint('kitchen', $orderId);

        jsonResponse(true, ['order_id' => $orderId, 'added_total' => $addedSubtotal], 'تمت إضافة الأصناف وتحديث الفاتورة بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'حدث خطأ: ' . $e->getMessage(), 500);
    }
}

function deleteItemFromOrder()
{
    $db = getDB();
    $user = getCurrentUser();
    
    if (!in_array($user['role'], ['cashier', 'admin'])) {
        jsonResponse(false, null, 'غير مصرح لك بحذف أصناف من الفاتورة', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int) ($input['order_id'] ?? 0);
    $itemId = (int) ($input['item_id'] ?? 0);

    if (!$orderId || !$itemId) jsonResponse(false, null, 'بيانات غير مكتملة', 400);

    $oStmt = $db->prepare("SELECT * FROM orders WHERE id=?");
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch();

    if (!$order) jsonResponse(false, null, 'الطلب غير موجود', 404);
    if (!in_array($order['status'], ['pending', 'sent_to_cashier', 'confirmed', 'in_progress', 'ready'])) {
        jsonResponse(false, null, 'لا يمكن حذف أجزاء من فاتورة مسددة أو ملغية', 400);
    }

    $itemQuery = $db->prepare("SELECT * FROM order_items WHERE id = ? AND order_id = ?");
    $itemQuery->execute([$itemId, $orderId]);
    $item = $itemQuery->fetch();
    if (!$item) {
        jsonResponse(false, null, 'الصنف غير موجود في هذا الطلب', 404);
    }

    if ($order['is_paid_once'] == 1 && $item['is_appended'] == 0) {
        jsonResponse(false, null, 'عفواً، لا يمكن حذف صنف تم تأكيده ودفع قيمته مسبقاً', 400);
    }

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM order_items WHERE id=? AND order_id=?")->execute([$itemId, $orderId]);
        
        $checkRemaining = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=?");
        $checkRemaining->execute([$orderId]);
        if ($checkRemaining->fetchColumn() == 0) {
            $db->prepare("UPDATE orders SET status='cancelled', subtotal=0, tax=0, service_charge=0, total=0, manual_discount=0 WHERE id=?")->execute([$orderId]);
            $db->commit();
            pushEvent('order_status_changed', ['order_id' => $orderId, 'status' => 'cancelled']);
            jsonResponse(true, null, 'تم حذف الصنف. لعدم وجود أصناف أخرى، تم الإلغاء');
        }

        $sumStmt = $db->prepare("SELECT SUM(subtotal) FROM order_items WHERE order_id=?");
        $sumStmt->execute([$orderId]);
        $newSubtotal = (float)$sumStmt->fetchColumn();
        
        $settings = getSettings();
        $taxRate = (float) ($settings['tax_rate'] ?? 0);
        $serviceRate = (float) ($settings['service_charge'] ?? 0);
        
        $newTax = round($newSubtotal * ($taxRate / 100), 2);
        $newService = round($newSubtotal * ($serviceRate / 100), 2);
        $newTotal = $newSubtotal + $newTax + $newService;

        $manualDisc = (float)$order['manual_discount'];
        $grandTotal = $newTotal - $manualDisc;
        if ($grandTotal < 0) $grandTotal = 0;

        $db->prepare("UPDATE orders SET subtotal=?, tax=?, service_charge=?, total=? WHERE id=?")->execute([$newSubtotal, $newTax, $newService, $grandTotal, $orderId]);
        $db->commit();
        
        pushEvent('order_status_changed', ['order_id' => $orderId, 'status' => 'item_deleted']);
        pushEvent('item_status_changed', ['order_id' => $orderId, 'message' => "تم حذف صنف من الطلب #{$order['order_number']}"]);
        
        jsonResponse(true, null, 'تم حذف الصنف وإعادة احتساب الفاتورة');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'حدث خطأ أثناء حذق الصنف: '.$e->getMessage(), 500);
    }
}

function applyManualDiscount()
{
    $db = getDB();
    $user = getCurrentUser();
    
    if ($user['role'] !== 'admin' && $user['role'] !== 'cashier') {
        jsonResponse(false, null, 'غير مصرح لك بإجراء هذا التعديل', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int) ($input['order_id'] ?? 0);
    $discountValue = (float) ($input['value'] ?? 0);
    $discountType = $input['type'] ?? 'fixed';
    $reason = trim($input['reason'] ?? '');
    $customerType = $input['customer_type'] ?? 'normal';
    $customerRef = trim($input['customer_ref'] ?? '');

    if (!$orderId) jsonResponse(false, null, 'معرف الطلب غير صحيح', 400);
    if ($discountValue < 0) jsonResponse(false, null, 'قيمة الخصم لا يمكن أن تكون سالبة', 400);
    if ($discountValue > 0 && empty($reason)) jsonResponse(false, null, 'يرجى إدخال سبب الخصم', 400);
    if (in_array($customerType, ['room', 'staff']) && empty($customerRef)) {
        jsonResponse(false, null, 'يرجى إدخال رقم الغرفة أو رقم الموظف', 400);
    }

    $stmt = $db->prepare("SELECT subtotal, tax, service_charge, manual_discount, status, order_number FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) jsonResponse(false, null, 'الطلب غير موجود', 404);
    if (in_array($order['status'], ['cancelled', 'delivered', 'paid', 'completed', 'refunded', 'partially_refunded'])) {
        jsonResponse(false, null, 'لا يمكن إضافة خصم لطلب تم سداده أو تسليمه مسبقاً', 400);
    }

    if ($discountType === 'percent' && $discountValue > 100) {
        jsonResponse(false, null, 'نسبة الخصم لا يمكن أن تتجاوز 100%', 400);
    }

    $maxAllowed = (float)$order['subtotal'] + (float)$order['tax'] + (float)$order['service_charge'];
    if ($discountType === 'fixed' && $discountValue > $maxAllowed) {
        jsonResponse(false, null, "قيمة الخصم لا يمكن أن تتجاوز إجمالي الفاتورة ({$maxAllowed} ريال)", 400);
    }

    $discountAmount = $discountValue;
    if ($discountType === 'percent') {
        $discountAmount = round($order['subtotal'] * ($discountValue / 100), 2);
        if ($discountValue > 0) $reason .= " ({$discountValue}%)"; 
    }

    $newTotal = $order['subtotal'] + $order['tax'] + $order['service_charge'] - $discountAmount;

    try {
        $db->prepare("UPDATE orders SET manual_discount=?, discount_reason=?, customer_type=?, customer_ref=?, total=? WHERE id=?")
           ->execute([$discountAmount, $reason, $customerType, $customerRef, $newTotal, $orderId]);

        logActivity("تعديل الطلب", "خصم: {$discountAmount}، نوع العميل: {$customerType}، المرجع: {$customerRef} للطلب #{$order['order_number']}");
        
        pushEvent('order_status_changed', [
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'message' => "تم تعديل بيانات الطلب #{$order['order_number']}"
        ]);

        jsonResponse(true, ['new_total' => $newTotal], 'تم حفظ التعديلات بنجاح');
    } catch (PDOException $e) {
        jsonResponse(false, null, 'فشل في حفظ التعديلات: ' . $e->getMessage(), 500);
    }
}

function updateOrderStatus()
{
    $db = getDB();
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int) ($input['order_id'] ?? 0);
    $status = $input['status'] ?? '';

    $allowed = ['pending', 'sent_to_cashier', 'confirmed', 'in_progress', 'ready', 'paid', 'cancelled', 'delivered', 'refunded', 'partially_refunded'];
    if (!$id || !in_array($status, $allowed))
        jsonResponse(false, null, 'بيانات غير صالحة', 400);

    $stmt = $db->prepare("SELECT status, total, subtotal FROM orders WHERE id=?");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    if (!$current)
        jsonResponse(false, null, 'الطلب غير موجود', 404);

    if ($status === 'paid' && $current['status'] === 'paid') {
        jsonResponse(true, null, 'تم تأكيد الدفع لهذا الطلب مسبقاً ✅');
    }
    if ($current['status'] === 'cancelled') {
        jsonResponse(false, null, 'لا يمكن تعديل حالة طلب ملغي', 400);
    }

    $confirmedStatuses = ['confirmed', 'paid', 'in_progress', 'ready', 'delivered'];
    if ($status === 'cancelled') {
        if ($current['status'] === 'paid') {
            jsonResponse(false, null, 'لا يمكن إلغاء الطلب بعد تأكيد الدفع ✅', 400);
        }
        if ($user['role'] === 'cashier' && in_array($current['status'], $confirmedStatuses)) {
            jsonResponse(false, null, 'لا يمكن إلغاء الطلب بعد تأكيده وإرساله للمطبخ. تواصل مع المدير لإتمام الإلغاء.', 403);
        }
    }

    $extra = '';
    $params = [$status];
    if ($status === 'paid' || $status === 'confirmed') {
        $method = $input['payment_method'] ?? 'cash';
        $ref = trim($input['wallet_reference'] ?? $input['payment_reference'] ?? '');
        $extra = ", paid_at=NOW(), cashier_id=?, payment_method=?, payment_reference=?";
        $params[] = $user['id'];
        $params[] = $method;
        $params[] = $ref ?: null;

        $paymentNote = trim($input['payment_note'] ?? '');
        if ($paymentNote !== '') {
            $extra .= ", notes = IF(notes IS NULL OR notes = '', ?, CONCAT(notes, ' | ', ?))";
            $params[] = $paymentNote;
            $params[] = $paymentNote;
        }

        if ($method === 'wallet') {
            $wId   = !empty($input['wallet_id'])   ? (int)$input['wallet_id']   : null;
            $wName = !empty($input['wallet_name'])  ? trim($input['wallet_name']) : null;
            if ($wId || $wName) {
                $extra .= ", wallet_id=?, wallet_name=?";
                $params[] = $wId;
                $params[] = $wName;
            }
        }

        $directName = trim($input['direct_name'] ?? '');
        if ($directName !== '') {
            $extra .= ", direct_name=?";
            $params[] = $directName;
        }
    } elseif ($status === 'ready') {
        $extra = ", ready_at=NOW()";
    } elseif ($status === 'delivered') {
        $extra = ", delivered_at=NOW()";
    } elseif ($status === 'refunded' || $status === 'partially_refunded') {
        $extra = ", refund_amount=?";
        if ($status === 'refunded') {
            $params[] = (float) $current['total'];
        } else {
            $rStmt = $db->prepare("SELECT SUM(subtotal) FROM order_items WHERE order_id=? AND status='rejected'");
            $rStmt->execute([$id]);
            $rejectedSubtotal = (float) $rStmt->fetchColumn();

            $originalSubtotal = (float) ($current['subtotal'] ?: 1);
            $proportion = $rejectedSubtotal / $originalSubtotal;
            $params[] = round($proportion * (float) $current['total'], 2);
        }
    } elseif ($status === 'cancelled') {
        $reason = trim($input['cancellation_reason'] ?? '');
        if ($reason !== '') {
            $extra = ", notes = IF(notes IS NULL OR notes = '', ?, CONCAT(notes, ' | ', ?))";
            $params[] = "سبب الإلغاء: " . $reason;
            $params[] = "سبب الإلغاء: " . $reason;
        }
    }
    $params[] = $id;

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE orders SET status=? $extra WHERE id=?")->execute($params);
        if ($status === 'paid') {
            $db->prepare("UPDATE orders SET is_paid_once = 1 WHERE id = ?")->execute([$id]);
        }
        $db->commit();

        $orderRow = $db->prepare("SELECT order_number, table_number FROM orders WHERE id=?");
        $orderRow->execute([$id]);
        $oData = $orderRow->fetch();

        pushEvent('order_status_changed', [
            'order_id' => $id,
            'order_number' => $oData ? $oData['order_number'] : '#',
            'table' => $oData ? $oData['table_number'] : '',
            'status' => $status,
            'by' => $user['name'],
        ]);

    } catch (PDOException $e) {
        jsonResponse(false, null, 'خطأ في قاعدة البيانات أثناء التحديث: ' . $e->getMessage());
    }

    // On confirm or paid: push to stations + auto-print kitchen
    if ($status === 'confirmed' || $status === 'paid') {
        $stStmt = $db->prepare("SELECT `value` FROM settings WHERE `key`='enable_stock_tracking'");
        $stStmt->execute();
        if ($stStmt->fetchColumn() === '1') {
            deductOrderStock($db, $id, $user);
        }
        distributeOrderToStations($db, $id, $status);

        // Auto-print Receipt — only for network-print cashiers.
        // Bluetooth cashiers handle printing client-side via printOrder() in JS.
        $cashierPrintType = $user['print_type'] ?? 'network';
        if ($cashierPrintType !== 'bluetooth') {
            triggerPrint('receipt', $id);
        }

        // ── Auto-print Kitchen ticket silently (ESC/POS via PowerShell — no dialog, no popup) ──
        // Works regardless of cashier print_type (network or bluetooth).
        // Failure is logged only, NEVER breaks the order flow.
        autoKitchenPrint($db, $id);
    }
    
    // On cancel: restore stock if deducted
    if ($status === 'cancelled') {
        $stStmt2 = $db->prepare("SELECT `value` FROM settings WHERE `key`='enable_stock_tracking'");
        $stStmt2->execute();
        if ($stStmt2->fetchColumn() === '1') {
            restoreOrderStock($db, $id, $user);
        }
    }

    // Log critical status changes
    $statusMap = ['paid' => 'دفع الطلب', 'cancelled' => 'إلغاء الطلب', 'refunded' => 'إرجاع كامل', 'partially_refunded' => 'إرجاع جزئي'];
    if (isset($statusMap[$status])) {
        logActivity($statusMap[$status], "طلب رقم {$oData['order_number']} بقيمة " . ($input['total'] ?? ''));
    }

    jsonResponse(true, null, 'تم تحديث حالة الطلب');
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTO KITCHEN PRINT — ESC/POS silent print on order confirmation
// Uses rawPrint() + buildKitchenESC() from print_direct_lib.php
// Failure is logged but NEVER blocks the order confirmation response.
// ─────────────────────────────────────────────────────────────────────────────
function autoKitchenPrint(PDO $db, int $orderId): void
{
    try {
        $settings = getSettings();
        $restName = trim(preg_replace('/[^\x20-\x7E]/', '', $settings['restaurant_name'] ?? '')) ?: 'Restaurant';

        // Fetch order with cashier + waiter + direct_name
        $oStmt = $db->prepare("
            SELECT o.order_number, o.table_number, o.created_at, o.notes, o.direct_name,
                   w.name AS waiter_name, c.name AS cashier_name
            FROM orders o
            LEFT JOIN users w ON o.waiter_id  = w.id
            LEFT JOIN users c ON o.cashier_id = c.id
            WHERE o.id = ?
        ");
        $oStmt->execute([$orderId]);
        $order = $oStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return;

        // Fetch non-rejected items
        $iStmt = $db->prepare("
            SELECT item_name_ar AS name, item_name_en AS name_en,
                   quantity AS qty, unit_price AS price,
                   subtotal AS total, notes
            FROM order_items
            WHERE order_id = ? AND status != 'rejected'
            ORDER BY id ASC
        ");
        $iStmt->execute([$orderId]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) return;

        // Get kitchen printer name from the `printers` table
        // (configured by admin in admin/printers.php → type='kitchen')
        $kitchenPrinter = getPrinterName($db, 'kitchen');
        if (!$kitchenPrinter) return; // No kitchen printer configured — skip silently

        // Build ESC/POS bytes and send
        $bytes  = buildKitchenESC($restName, $order, $items);
        $result = rawPrint($kitchenPrinter, $bytes);

        // Log result (non-blocking)
        try {
            if ($result['ok']) {
                $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")
                   ->execute([$orderId]);
            }
            $db->prepare("INSERT INTO print_logs (user_id, order_id, printer_type, status, error_message) VALUES (0, ?, 'kitchen_auto', ?, ?)")
               ->execute([$orderId, $result['ok'] ? 'success' : 'failed', $result['ok'] ? null : $result['msg']]);
        } catch (\Exception $logEx) {
            error_log('[autoKitchenPrint] log failed: ' . $logEx->getMessage());
        }

    } catch (\Exception $e) {
        // Never let print failure break order confirmation
        error_log('[autoKitchenPrint] error: ' . $e->getMessage());
    }
}

function distributeOrderToStations(PDO $db, int $orderId, string $currentStatus = 'confirmed')
{
    $stmt = $db->prepare("
        SELECT oi.*, c.name_ar as cat_name_ar, c.id as cat_id
        FROM order_items oi 
        JOIN categories c ON oi.category_id = c.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    $byCategory = [];
    foreach ($items as $item) {
        $byCategory[$item['cat_id']][] = $item;
    }

    $stations = $db->query("
        SELECT u.id, u.name, r.name as role, 
               GROUP_CONCAT(ucp.category_id) as categories
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN user_category_permissions ucp ON ucp.user_id = u.id
        WHERE r.name IN ('chef','juice_bar') AND u.is_active=1
        GROUP BY u.id
    ")->fetchAll();

    $orderData = $db->prepare("SELECT * FROM orders WHERE id=?");
    $orderData->execute([$orderId]);
    $orderInfo = $orderData->fetch();

    foreach ($stations as $station) {
        $stationCats = $station['categories'] ? explode(',', $station['categories']) : [];
        $stationItems = [];

        if (empty($stationCats)) {
            $stationItems = $items;
        } else {
            foreach ($stationCats as $catId) {
                if (isset($byCategory[$catId])) {
                    $stationItems = array_merge($stationItems, $byCategory[$catId]);
                }
            }
        }

        if (!empty($stationItems)) {
            pushEvent('station_order', [
                'order_id'     => $orderId,
                'order_number' => $orderInfo['order_number'],
                'table'        => $orderInfo['table_number'],
                'notes'        => $orderInfo['notes'],
                'items'        => $stationItems,
                'target_user'  => $station['id'],
            ], $station['role']);

            try {
                $queueCheck = $db->prepare("SELECT id FROM print_queue WHERE order_id=? AND station_user_id=?");
                $queueCheck->execute([$orderId, $station['id']]);
                if (!$queueCheck->fetch()) {
                    $db->prepare("INSERT INTO print_queue (order_id, station_user_id) VALUES (?,?)")
                       ->execute([$orderId, $station['id']]);
                }
            } catch (\Exception $queueEx) {
                error_log("print_queue insert failed: " . $queueEx->getMessage());
            }
        }
    }

    if ($currentStatus !== 'paid') {
        $db->prepare("UPDATE orders SET status='in_progress' WHERE id=?")->execute([$orderId]);
    }
}

function updateItemStatus()
{
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int) ($input['item_id'] ?? 0);
    $status = $input['status'] ?? '';

    $allowed = ['pending', 'in_progress', 'ready', 'served', 'rejected'];
    if (!$id || !in_array($status, $allowed))
        jsonResponse(false, null, 'بيانات غير صالحة', 400);

    $reason = $input['reason'] ?? null;
    $sql = "UPDATE order_items SET 
            status=?, 
            rejection_reason=?,
            prep_start_time = IF(? = 'in_progress' AND prep_start_time IS NULL, NOW(), prep_start_time),
            prep_end_time = IF(? = 'ready' AND prep_start_time IS NOT NULL AND prep_end_time IS NULL, NOW(), prep_end_time)
            WHERE id=?";
    $db->prepare($sql)->execute([$status, $reason, $status, $status, $id]);

    $oRow = $db->prepare("SELECT oi.order_id, o.order_number FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.id=?");
    $oRow->execute([$id]);
    $oData = $oRow->fetch();

    pushEvent('item_status_changed', ['item_id' => $id, 'order_id' => $oData['order_id'], 'order_number' => $oData['order_number'], 'status' => $status]);

    $orderId = (int) $oData['order_id'];

    if ($status === 'in_progress') {
        $stmt = $db->prepare("UPDATE orders SET status='in_progress' WHERE id=? AND status IN ('sent_to_cashier','confirmed')");
        $stmt->execute([$orderId]);
        if ($stmt->rowCount() > 0) {
            pushEvent('order_status_changed', ['order_id' => $orderId, 'status' => 'in_progress', 'order_number' => $oData['order_number']]);
        }
    }
    $check = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=? AND status NOT IN ('ready','served','rejected')");
    $check->execute([$orderId]);
    if ($check->fetchColumn() == 0) {
        $db->prepare("UPDATE orders SET ready_at = IFNULL(ready_at, NOW()) WHERE id=?")->execute([$orderId]);
        $stmt = $db->prepare("UPDATE orders SET status='ready' WHERE id=? AND status IN ('confirmed','in_progress')");
        $stmt->execute([$orderId]);
        if ($stmt->rowCount() > 0) {
            pushEvent('order_status_changed', ['order_id' => $orderId, 'status' => 'ready', 'order_number' => $oData['order_number']]);
        }
    }

    jsonResponse(true, null, 'تم تحديث حالة الصنف');
}

function updateOrderItemsStatus()
{
    $db = getDB();
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $orderId = (int) ($input['order_id'] ?? 0);
    $status = $input['status'] ?? '';

    $allowed = ['pending', 'in_progress', 'ready', 'served', 'rejected'];
    if (!$orderId || !in_array($status, $allowed))
        jsonResponse(false, null, 'بيانات غير صالحة', 400);

    $stmtCats = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id = ?");
    $stmtCats->execute([$user['id']]);
    $allowedCats = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    $sql = "UPDATE order_items SET 
            status=?,
            prep_start_time = IF(? = 'in_progress' AND prep_start_time IS NULL, NOW(), prep_start_time),
            prep_end_time = IF(? = 'ready' AND prep_start_time IS NOT NULL AND prep_end_time IS NULL, NOW(), prep_end_time)
            WHERE order_id=?";
    $params = [$status, $status, $status, $orderId];

    if (!empty($allowedCats) && $user['role'] !== 'admin') {
        $placeholders = implode(',', array_fill(0, count($allowedCats), '?'));
        $sql .= " AND category_id IN ($placeholders)";
        $params = array_merge($params, $allowedCats);
    }

    $db->prepare($sql)->execute($params);

    $orderRow = $db->prepare("SELECT order_number FROM orders WHERE id=?");
    $orderRow->execute([$orderId]);
    $oData = $orderRow->fetch();

    if ($status === 'in_progress') {
        $db->prepare("UPDATE orders SET status='in_progress' WHERE id=? AND status IN ('sent_to_cashier','confirmed')")->execute([$orderId]);
    }

    $check = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=? AND status NOT IN ('ready','served','rejected')");
    $check->execute([$orderId]);
    if ($check->fetchColumn() == 0) {
        $db->prepare("UPDATE orders SET ready_at = IFNULL(ready_at, NOW()) WHERE id=?")->execute([$orderId]);
        $db->prepare("UPDATE orders SET status='ready' WHERE id=? AND status IN ('confirmed','in_progress')")->execute([$orderId]);
        pushEvent('order_status_changed', ['order_id' => $orderId, 'status' => 'ready', 'order_number' => $oData ? $oData['order_number'] : '#']);
    } else {
        pushEvent('item_status_changed', ['order_id' => $orderId, 'status' => $status, 'order_number' => $oData ? $oData['order_number'] : '#', 'message' => 'batch_update']);
    }

    jsonResponse(true, null, 'تم تحديث كافة الأصناف');
}

function deleteOrder()
{
    $db = getDB();
    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);

    if (!$id)
        jsonResponse(false, null, 'معرف غير صالح', 400);

    try {
        $db->beginTransaction();

        $oRow = $db->prepare("SELECT * FROM orders WHERE id=?");
        $oRow->execute([$id]);
        $order = $oRow->fetch();

        if (!$order)
            jsonResponse(false, null, 'الطلب غير موجود', 404);

        if ($order['status'] === 'paid' && $user['role'] !== 'admin') {
            jsonResponse(false, null, 'لا يمكن حذف طلب تم سداده. يرجى استخدام نظام المرتجعات إذا لزم الأمر.', 403);
        }

        if ($user['role'] !== 'admin') {
            if ($order['waiter_id'] != $user['id']) {
                jsonResponse(false, null, 'لا يمكنك حذف هذا الطلب', 403);
            }
            if (!in_array($order['status'], ['pending', 'sent_to_cashier'])) {
                jsonResponse(false, null, 'لا يمكن حذف أو إلغاء طلب تم تأكيده أو إرساله للمطبخ. تواصل مع المدير.', 400);
            }
        }

        $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
        $db->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);

        $db->commit();
        pushEvent('order_deleted', ['order_id' => $id]);
        logActivity("حذف طلب نهائياً", "تم حذف طلب رقم {$order['order_number']} من الجذور");
        jsonResponse(true, null, 'تم حذف الطلب من الجذور بنجاح');
    } catch (Exception $e) {
        if ($db->inTransaction())
            $db->rollBack();
        jsonResponse(false, null, 'خطأ أثناء الحذف: ' . $e->getMessage(), 500);
    }
}

function deliverAllActiveOrders() {
    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE orders SET status = 'delivered' WHERE status NOT IN ('delivered', 'cancelled', 'refunded')");
        $stmt->execute();
        $rowCount = $stmt->rowCount();
        
        $itemStmt = $db->prepare("UPDATE order_items oi JOIN orders o ON oi.order_id = o.id SET oi.status = 'delivered' WHERE o.status = 'delivered' AND oi.status NOT IN ('delivered', 'rejected')");
        $itemStmt->execute();
        
        jsonResponse(true, ['affected' => $rowCount], "تم تسليم $rowCount طلب بنجاح");
    } catch (PDOException $e) {
        jsonResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
    }
}

function deductOrderStock(PDO $db, int $orderId, array $user)
{
    $stmt = $db->prepare("SELECT order_number, is_stock_deducted FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || $order['is_stock_deducted'] == 1) return;

    $orderNumber = $order['order_number'];

    $iStmt = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id=? AND status != 'rejected'");
    $iStmt->execute([$orderId]);
    $items = $iStmt->fetchAll();

    $qtyPerItem = [];
    foreach ($items as $item) {
        $iId = (int)$item['item_id'];
        if ($iId > 0) {
            $qtyPerItem[$iId] = ($qtyPerItem[$iId] ?? 0) + (float)$item['quantity'];
        }
    }

    foreach ($qtyPerItem as $stockItemId => $deductQty) {
        $sRow = $db->prepare("SELECT stock_qty FROM item_stock WHERE item_id=?");
        $sRow->execute([$stockItemId]);
        $stockRow = $sRow->fetch();
        if (!$stockRow) continue; 

        $before = (float)$stockRow['stock_qty'];
        $after  = max(0, $before - $deductQty);

        $db->prepare("UPDATE item_stock SET stock_qty=?, updated_by=? WHERE item_id=?")
           ->execute([$after, $user['id'], $stockItemId]);

        $nameRow = $db->prepare("SELECT name_ar FROM items WHERE id=?");
        $nameRow->execute([$stockItemId]);
        $itemName = $nameRow->fetchColumn() ?: 'صنف #' . $stockItemId;

        $db->prepare("INSERT INTO item_stock_log (item_id, item_name_ar, action_type, qty_before, qty_change, qty_after, note, user_id, user_name)
                      VALUES (?, ?, 'order_deduct', ?, ?, ?, ?, ?, ?)")
           ->execute([$stockItemId, $itemName, $before, -$deductQty, $after, "تأكيد طلب رقم $orderNumber", $user['id'], $user['name']]);
    }

    $db->prepare("UPDATE orders SET is_stock_deducted=1 WHERE id=?")->execute([$orderId]);
}

function restoreOrderStock(PDO $db, int $orderId, array $user)
{
    $stmt = $db->prepare("SELECT order_number, is_stock_deducted FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || $order['is_stock_deducted'] == 0) return;

    $orderNumber = $order['order_number'];

    $iStmt = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id=? AND status != 'rejected'");
    $iStmt->execute([$orderId]);
    $items = $iStmt->fetchAll();

    $qtyPerItem = [];
    foreach ($items as $item) {
        $iId = (int)$item['item_id'];
        if ($iId > 0) {
            $qtyPerItem[$iId] = ($qtyPerItem[$iId] ?? 0) + (float)$item['quantity'];
        }
    }

    foreach ($qtyPerItem as $stockItemId => $restoreQty) {
        $sRow = $db->prepare("SELECT stock_qty FROM item_stock WHERE item_id=?");
        $sRow->execute([$stockItemId]);
        $stockRow = $sRow->fetch();
        if (!$stockRow) continue;

        $before = (float)$stockRow['stock_qty'];
        $after  = $before + $restoreQty;

        $db->prepare("UPDATE item_stock SET stock_qty=?, updated_by=? WHERE item_id=?")
           ->execute([$after, $user['id'], $stockItemId]);

        $nameRow = $db->prepare("SELECT name_ar FROM items WHERE id=?");
        $nameRow->execute([$stockItemId]);
        $itemName = $nameRow->fetchColumn() ?: 'صنف #' . $stockItemId;

        $db->prepare("INSERT INTO item_stock_log (item_id, item_name_ar, action_type, qty_before, qty_change, qty_after, note, user_id, user_name)
                      VALUES (?, ?, 'order_cancel', ?, ?, ?, ?, ?, ?)")
           ->execute([$stockItemId, $itemName, $before, $restoreQty, $after, "إلغاء طلب رقم $orderNumber", $user['id'], $user['name']]);
    }

    $db->prepare("UPDATE orders SET is_stock_deducted=0 WHERE id=?")->execute([$orderId]);
}

function updatePaymentNote()
{
    $db = getDB();
    $user = getCurrentUser();
    
    if (!in_array($user['role'], ['cashier', 'admin'])) {
        jsonResponse(false, null, 'غير مصرح لك بتعديل الملاحظات', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int) ($input['order_id'] ?? 0);
    $paymentNote = trim($input['payment_note'] ?? '');

    if (!$orderId) {
        jsonResponse(false, null, 'معرف الطلب غير صحيح', 400);
    }

    $stmt = $db->prepare("SELECT notes, order_number FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        jsonResponse(false, null, 'الطلب غير موجود', 404);
    }

    $existingNotes = $order['notes'] ?? '';
    $formattedNote = "ملاحظة الدفع: " . $paymentNote;

    $cleanedNotes = preg_replace('/(\s*\|\s*)?ملاحظة الدفع:[^|]*/u', '', $existingNotes);
    $cleanedNotes = trim($cleanedNotes, ' |');

    if ($paymentNote !== '') {
        if ($cleanedNotes !== '') {
            $newNotes = $cleanedNotes . ' | ' . $formattedNote;
        } else {
            $newNotes = $formattedNote;
        }
    } else {
        $newNotes = $cleanedNotes;
    }

    $db->prepare("UPDATE orders SET notes=? WHERE id=?")->execute([$newNotes, $orderId]);
    logActivity("تعديل ملاحظة الدفع", "تحديث ملاحظة الدفع للطلب #{$order['order_number']}: {$paymentNote}");
    
    pushEvent('order_status_changed', [
        'order_id' => $orderId,
        'status' => 'notes_updated',
        'message' => 'تم تحديث ملاحظة الدفع للطلب'
    ]);

    jsonResponse(true, ['notes' => $newNotes], 'تم تحديث ملاحظة الدفع بنجاح');
}
