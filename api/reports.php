<?php
require_once __DIR__ . '/../config/db.php';
startSession();
$_currentUser = getCurrentUser();
// Allow: admin/cashier/accountant by role, OR any user with custom 'reports' permission
if (!$_currentUser) { http_response_code(401); die(json_encode(['success'=>false,'message'=>'غير مصرح'])); }
$_isAllowedRole = in_array($_currentUser['role'], ['admin','cashier','accountant']);
$_hasReportPerm = !empty($_currentUser['permissions']) && in_array('reports', json_decode($_currentUser['permissions'], true) ?? []);
if (!$_isAllowedRole && !$_hasReportPerm) { http_response_code(403); die(json_encode(['success'=>false,'message'=>'ليس لديك صلاحية'])); }

$action = $_GET['action'] ?? 'daily';

switch ($action) {
    case 'daily':    getDailyReport();    break;
    case 'range':    getRangeReport();    break;
    case 'top_items': getTopItems();      break;
    case 'summary':  getSummary();        break;
    case 'dashboard_charts': getDashboardCharts(); break;
    default: jsonResponse(false, null, 'إجراء غير معروف', 400);
}

function getDailyReport() {
    $db   = getDB();
    $date = $_GET['date'] ?? date('Y-m-d');

    // Fetch orders with item_count
    $stmt = $db->prepare("
        SELECT o.*, w.name as waiter_name, c.name as cashier_name,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN users w ON o.waiter_id = w.id
        LEFT JOIN users c ON o.cashier_id = c.id
        WHERE DATE(o.created_at) = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$date]);
    $orders = $stmt->fetchAll();

    // Attach items with explicit ASSOC mode to guarantee clean JSON arrays
    foreach ($orders as &$order) {
        $iStmt = $db->prepare("SELECT item_name_ar, quantity, unit_price, subtotal, status FROM order_items WHERE order_id=? ORDER BY id ASC");
        $iStmt->execute([$order['id']]);
        $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order); // break reference

    // Stats (Synchronized with Sales/Revenue)
    $statStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN 1 ELSE 0 END) as paid_orders,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN (total - refund_amount) ELSE 0 END), 0) as total_revenue,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' THEN (total - refund_amount) ELSE 0 END), 0) as total_wallet,
            IFNULL(SUM(refund_amount), 0) as refunded_amount,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN manual_discount ELSE 0 END), 0) as total_discounts,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN service_charge ELSE 0 END), 0) as total_service,
            SUM(CASE WHEN status IN ('cancelled','refunded') THEN 1 ELSE 0 END) as cancelled_orders,
            AVG(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN (total - refund_amount) ELSE NULL END) as avg_order_value,
            (SELECT IFNULL(SUM(oi.quantity), 0) 
             FROM order_items oi 
             JOIN orders o2 ON oi.order_id = o2.id 
             WHERE DATE(o2.created_at) = ? AND o2.paid_at IS NOT NULL AND o2.status != 'refunded') as total_pieces
        FROM orders WHERE DATE(created_at) = ?
    ");
    $statStmt->execute([$date, $date]);
    $stats = $statStmt->fetch();

    // Top items for this day (Only finalized sales to match Revenue)
    $itemStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, i.item_number, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue, c.icon as cat_icon
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) = ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY oi.item_id, oi.item_name_ar, i.item_number, c.icon
        ORDER BY total_qty DESC
    ");
    $itemStmt->execute([$date]);
    $topItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch cashier sales breakdown for this day
    $cashierSalesStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, u.name as cashier_name, SUM(oi.quantity) as qty
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) = ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.status != 'rejected'
        GROUP BY oi.item_id, oi.item_name_ar, u.id, u.name
    ");
    $cashierSalesStmt->execute([$date]);
    $cashierSales = $cashierSalesStmt->fetchAll(PDO::FETCH_ASSOC);

    $salesMap = [];
    foreach ($cashierSales as $cs) {
        $key = $cs['item_name_ar'] . '_' . ($cs['item_id'] ?? 0);
        $salesMap[$key][] = $cs['cashier_name'] . ' (' . $cs['qty'] . ')';
    }

    foreach ($topItems as &$item) {
        $key = $item['item_name_ar'] . '_' . ($item['item_id'] ?? 0);
        $item['cashier_breakdown'] = isset($salesMap[$key]) ? implode(' | ', $salesMap[$key]) : '';
    }
    unset($item);

    jsonResponse(true, ['orders' => $orders, 'stats' => $stats, 'top_items' => $topItems, 'date' => $date]);
}

function getRangeReport() {
    $db    = getDB();
    $from  = $_GET['from'] ?? date('Y-m-01');
    $to    = $_GET['to']   ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT 
            DATE(o.created_at) as date,
            COUNT(o.id) as orders_count,
            SUM(CASE WHEN o.paid_at IS NOT NULL AND o.status != 'refunded' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN o.paid_at IS NOT NULL AND o.status != 'refunded' THEN (o.total - o.refund_amount) ELSE 0 END) as revenue
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE(o.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();

    $total = $db->prepare("SELECT SUM(total - refund_amount) as total FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at) BETWEEN ? AND ?");
    $total->execute([$from, $to]);
    $totalRev = $total->fetch();

    $walletTotalStmt = $db->prepare("SELECT SUM(total - refund_amount) as total FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE(created_at) BETWEEN ? AND ?");
    $walletTotalStmt->execute([$from, $to]);
    $walletTotal = $walletTotalStmt->fetch();

    // Fetch cashier sales breakdown for this range
    $cashierSalesStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, u.name as cashier_name, SUM(oi.quantity) as qty
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.status != 'rejected'
        GROUP BY oi.item_id, oi.item_name_ar, u.id, u.name
    ");
    $cashierSalesStmt->execute([$from, $to]);
    $cashierSales = $cashierSalesStmt->fetchAll(PDO::FETCH_ASSOC);

    $salesMap = [];
    foreach ($cashierSales as $cs) {
        $key = $cs['item_name_ar'] . '_' . ($cs['item_id'] ?? 0);
        $salesMap[$key][] = $cs['cashier_name'] . ' (' . $cs['qty'] . ')';
    }

    $itemStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, i.item_number, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue, c.icon as cat_icon
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY oi.item_id, oi.item_name_ar, i.item_number, c.icon
        ORDER BY total_qty DESC
    ");
    $itemStmt->execute([$from, $to]);
    $topItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($topItems as &$item) {
        $key = $item['item_name_ar'] . '_' . ($item['item_id'] ?? 0);
        $item['cashier_breakdown'] = isset($salesMap[$key]) ? implode(' | ', $salesMap[$key]) : '';
    }
    unset($item);

    jsonResponse(true, [
        'rows' => $rows,
        'total_revenue' => $totalRev['total'] ?? 0,
        'total_wallet' => $walletTotal['total'] ?? 0,
        'top_items' => $topItems,
        'from' => $from,
        'to' => $to
    ]);
}

function getTopItems() {
    $db   = getDB();
    $date = $_GET['date'] ?? date('Y-m-d');
    $limit = (int)($_GET['limit'] ?? 10);

    $stmt = $db->prepare("
        SELECT oi.item_name_ar, oi.item_name_en, c.name_ar as cat_name,
               SUM(oi.quantity) as total_qty,
               SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        WHERE DATE(o.created_at) = ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY oi.item_id, oi.item_name_ar, oi.item_name_en, c.name_ar
        ORDER BY total_qty DESC
        LIMIT ?
    ");
    $stmt->execute([$date, $limit]);
    jsonResponse(true, $stmt->fetchAll());
}

function getSummary() {
    $db = getDB();
    $today = date('Y-m-d');
    $month = date('Y-m');
    
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    // Always fetch actual today's revenue
    $todayRevStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at)=?");
    $todayRevStmt->execute([$today]);
    $todayRev = (float)$todayRevStmt->fetchColumn();

    // Always fetch actual today's wallet revenue (deposits)
    $todayWalletStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE(created_at)=?");
    $todayWalletStmt->execute([$today]);
    $todayWallet = (float)$todayWalletStmt->fetchColumn();

    // Always fetch global pending orders
    $pendingCount = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','sent_to_cashier','confirmed','in_progress')")->fetchColumn();

    // Fetch Period Revenue based on filter (or current month if no filter)
    $periodRev = 0;
    $periodWallet = 0;
    if ($from && $to) {
        $revStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $revStmt->execute([$from, $to]);
        $periodRev = (float)$revStmt->fetchColumn();

        $walletStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $walletStmt->execute([$from, $to]);
        $periodWallet = (float)$walletStmt->fetchColumn();
    } else {
        $monthRevStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE_FORMAT(created_at,'%Y-%m')=?");
        $monthRevStmt->execute([$month]);
        $periodRev = (float)$monthRevStmt->fetchColumn();

        $monthWalletStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE_FORMAT(created_at,'%Y-%m')=?");
        $monthWalletStmt->execute([$month]);
        $periodWallet = (float)$monthWalletStmt->fetchColumn();
    }

    jsonResponse(true, [
        'today_revenue'   => $todayRev,
        'period_revenue'  => $periodRev,
        'pending_orders'  => (int)$pendingCount,
        'today_wallet'    => $todayWallet,
        'period_wallet'   => $periodWallet,
    ]);
}

function getDashboardCharts() {
    $db = getDB();
    
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    
    // 1. Revenue Trend (Dynamic Days)
    $trendData = [];
    $begin = new DateTime($from);
    $end = new DateTime($to);
    $end->modify('+1 day'); // Include end date
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($begin, $interval, $end);
    
    foreach ($period as $dt) {
        $dateStr = $dt->format("Y-m-d");
        $trendData[$dateStr] = ['date' => $dateStr, 'revenue' => 0, 'orders' => 0];
    }
    
    $trendStmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(total - refund_amount) as revenue,
            COUNT(id) as orders
        FROM orders
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? AND paid_at IS NOT NULL AND status != 'refunded'
        GROUP BY DATE(created_at)
    ");
    $trendStmt->execute([$from, $to]);
    $trendResults = $trendStmt->fetchAll();
    foreach ($trendResults as $row) {
        if (isset($trendData[$row['date']])) {
            $trendData[$row['date']]['revenue'] = (float)$row['revenue'];
            $trendData[$row['date']]['orders'] = (int)$row['orders'];
        }
    }
    
    // Final trend array
    $trendFinal = array_values($trendData);
    
    // 2. Top Waiters (Date Range)
    $waitersStmt = $db->prepare("
        SELECT 
            u.name as waiter_name,
            COUNT(o.id) as orders_count,
            SUM(o.total - o.refund_amount) as total_revenue
        FROM orders o
        JOIN users u ON o.waiter_id = u.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY u.id, u.name
        ORDER BY orders_count DESC
        LIMIT 5
    ");
    $waitersStmt->execute([$from, $to]);
    $topWaiters = $waitersStmt->fetchAll();
    
    // 2.5 Top Cashiers (Date Range)
    $cashiersStmt = $db->prepare("
        SELECT 
            u.name as cashier_name,
            COUNT(o.id) as orders_count,
            SUM(o.total - o.refund_amount) as total_revenue
        FROM orders o
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY u.id, u.name
        ORDER BY total_revenue DESC
    ");
    $cashiersStmt->execute([$from, $to]);
    $topCashiers = $cashiersStmt->fetchAll();
    
    // 3. Top Categories (Date Range)
    $catStmt = $db->prepare("
        SELECT 
            c.name_ar as cat_name,
            SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY c.id, c.name_ar
        ORDER BY total_revenue DESC
    ");
    $catStmt->execute([$from, $to]);
    $topCategories = $catStmt->fetchAll();
    
    // 4. Current Status Snapshot (Date Range)
    $statusStmt = $db->prepare("
        SELECT status, COUNT(id) as count
        FROM orders
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
        GROUP BY status
    ");
    $statusStmt->execute([$from, $to]);
    $statuses = $statusStmt->fetchAll();

    // Auto-fix old orders that have wallet_id but missing wallet_name
    $db->exec("
        UPDATE orders o
        JOIN wallets w ON o.wallet_id = w.id
        SET o.wallet_name = CONCAT(w.name, ' (', w.account_number, ')')
        WHERE o.payment_method = 'wallet'
          AND (o.wallet_name IS NULL OR o.wallet_name = '')
          AND o.wallet_id IS NOT NULL
    ");

    // 5. Wallets Breakdown (Date Range)
    $walletsStmt = $db->prepare("
        SELECT 
            CASE 
                WHEN (o.wallet_name IS NULL OR o.wallet_name = '') AND w.name IS NOT NULL
                    THEN CONCAT(w.name, ' (', w.account_number, ')')
                WHEN o.wallet_name IS NOT NULL AND o.wallet_name != '' THEN o.wallet_name
                ELSE 'محفظة غير محددة'
            END as wallet_name,
            COUNT(o.id) as orders_count,
            SUM(o.total - o.refund_amount) as total_revenue
        FROM orders o
        LEFT JOIN wallets w ON o.wallet_id = w.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? 
          AND o.paid_at IS NOT NULL 
          AND o.status != 'refunded' 
          AND o.payment_method = 'wallet'
        GROUP BY wallet_name
        ORDER BY total_revenue DESC
    ");
    $walletsStmt->execute([$from, $to]);
    $walletsData = $walletsStmt->fetchAll();
    
    jsonResponse(true, [
        'trend' => $trendFinal,
        'waiters' => $topWaiters,
        'cashiers' => $topCashiers,
        'categories' => $topCategories,
        'statuses' => $statuses,
        'wallets' => $walletsData
    ]);
}
