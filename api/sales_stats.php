<?php
/**
 * api/sales_stats.php
 * Sales statistics + manual sales entry API.
 * Roles: admin, inventory_monitor
 */
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin', 'inventory_monitor']);

$db   = getDB();
$user = getCurrentUser();

// ── Read action from GET, POST, or JSON body ──────────────────────────────────
$jsonBody = null;
$action   = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw      = file_get_contents('php://input');
    $jsonBody = json_decode($raw, true);
    $action   = $jsonBody['action'] ?? '';
}

// ── Auto-create manual_sales table if missing ────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS manual_sales (
        id          INT PRIMARY KEY AUTO_INCREMENT,
        item_id     INT NOT NULL,
        quantity    DECIMAL(10,2) NOT NULL DEFAULT 1,
        sale_date   DATE NOT NULL,
        notes       VARCHAR(255) DEFAULT NULL,
        created_by  INT DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (sale_date),
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* already exists */ }

// ── GET: daily sales from system (orders) ────────────────────────────────────
if ($action === 'system_sales') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $stmt = $db->prepare("
        SELECT
            oi.item_name_en   AS name_en,
            oi.item_name_ar   AS name_ar,
            oi.item_id,
            i.item_number,
            SUM(oi.quantity)  AS qty_sold
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) = ?
          AND o.status NOT IN ('cancelled', 'rejected')
          AND oi.status NOT IN ('cancelled', 'rejected')
        GROUP BY oi.item_id, oi.item_name_en, oi.item_name_ar, i.item_number
        ORDER BY qty_sold DESC
    ");
    $stmt->execute([$date]);
    echo json_encode(['success' => true, 'sales' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'date' => $date], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET: manual sales for a date ─────────────────────────────────────────────
if ($action === 'manual_sales_get') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $stmt = $db->prepare("
        SELECT ms.id, ms.item_id, ms.quantity, ms.notes,
               i.name_ar, i.name_en, i.item_number
        FROM manual_sales ms
        JOIN items i ON i.id = ms.item_id
        WHERE ms.sale_date = ?
        ORDER BY ms.id ASC
    ");
    $stmt->execute([$date]);
    echo json_encode(['success' => true, 'sales' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'date' => $date], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET: items list for dropdown ──────────────────────────────────────────────
if ($action === 'items_list') {
    $stmt = $db->query("SELECT id, name_ar, name_en FROM items WHERE is_available = 1 ORDER BY name_ar ASC");
    echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST: save manual sale row ────────────────────────────────────────────────
if ($action === 'manual_sale_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = $jsonBody ?? [];
    $itemId = (int)($data['item_id'] ?? 0);
    $qty    = (float)($data['quantity'] ?? 0);
    $date   = $data['date'] ?? date('Y-m-d');
    $notes  = trim($data['notes'] ?? '');

    if (!$itemId || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']); exit;
    }
    $stmt = $db->prepare("INSERT INTO manual_sales (item_id, quantity, sale_date, notes, created_by) VALUES (?,?,?,?,?)");
    $stmt->execute([$itemId, $qty, $date, $notes, $user['id']]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── DELETE: remove manual sale row ───────────────────────────────────────────
if ($action === 'manual_sale_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $jsonBody ?? [];
    $id   = (int)($data['id'] ?? 0);
    $db->prepare("DELETE FROM manual_sales WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}


// ── GET: comparison report ────────────────────────────────────────────────────
if ($action === 'comparison') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $mode = $_GET['mode'] ?? 'system'; // system | manual

    if ($mode === 'system') {
        // Theoretical consumption from system orders
        $salesStmt = $db->prepare("
            SELECT oi.item_id, SUM(oi.quantity) AS qty_sold
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE DATE(o.created_at) = ?
              AND o.status NOT IN ('cancelled','rejected')
              AND oi.status NOT IN ('cancelled','rejected')
            GROUP BY oi.item_id
        ");
    } else {
        // Theoretical consumption from manual entries
        $salesStmt = $db->prepare("
            SELECT item_id, SUM(quantity) AS qty_sold
            FROM manual_sales
            WHERE sale_date = ?
            GROUP BY item_id
        ");
    }
    $salesStmt->execute([$date]);
    $salesMap = [];
    foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $salesMap[$r['item_id']] = (float)$r['qty_sold'];
    }

    // Theoretical ingredient consumption
    $theoretical = []; // ingredient_id => qty
    if (!empty($salesMap)) {
        $ids  = implode(',', array_map('intval', array_keys($salesMap)));
        $ingStmt = $db->query("
            SELECT ii.item_id, ii.ingredient_id, ii.quantity_per_portion,
                   ing.name_ar, ing.name_en, ing.unit
            FROM item_ingredients ii
            JOIN ingredients ing ON ing.id = ii.ingredient_id
            WHERE ii.item_id IN ($ids)
        ");
        foreach ($ingStmt->fetchAll(PDO::FETCH_ASSOC) as $ing) {
            $sold = $salesMap[$ing['item_id']] ?? 0;
            $key  = $ing['ingredient_id'];
            if (!isset($theoretical[$key])) {
                $theoretical[$key] = [
                    'ingredient_id' => $ing['ingredient_id'],
                    'name_ar'       => $ing['name_ar'],
                    'name_en'       => $ing['name_en'],
                    'unit'          => $ing['unit'],
                    'theoretical'   => 0,
                ];
            }
            $theoretical[$key]['theoretical'] += $ing['quantity_per_portion'] * $sold;
        }
    }

    // Actual inventory entries for the day
    $invStmt = $db->prepare("
        SELECT ingredient_id, SUM(quantity) AS actual
        FROM inventory_transactions
        WHERE DATE(created_at) = ? AND type = 'out'
        GROUP BY ingredient_id
    ");
    $invStmt->execute([$date]);
    $actualMap = [];
    foreach ($invStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $actualMap[$r['ingredient_id']] = (float)$r['actual'];
    }

    // Build result
    $result = [];
    foreach ($theoretical as $key => $row) {
        $actual = $actualMap[$key] ?? null;
        $diff   = ($actual !== null) ? round($actual - $row['theoretical'], 3) : null;
        $result[] = [
            'name_ar'     => $row['name_ar'],
            'name_en'     => $row['name_en'],
            'unit'        => $row['unit'],
            'theoretical' => round($row['theoretical'], 3),
            'actual'      => $actual,
            'diff'        => $diff,
            'status'      => $diff === null ? 'no_data' : ($diff > 0.05 ? 'over' : ($diff < -0.05 ? 'under' : 'ok')),
        ];
    }

    echo json_encode(['success' => true, 'date' => $date, 'mode' => $mode, 'comparison' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
