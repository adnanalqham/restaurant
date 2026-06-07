<?php
/**
 * Print Queue API
 * GET  ?action=pending  => returns unprinted jobs
 * POST ?action=mark_done => marks a job as printed { id: INT }
 *
 * Auth: Session cookie (browser) OR X-API-Key header (mobile app)
 *       OR Flutter/Dart User-Agent detection (when app omits API key header)
 */
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// ─── Auth ─────────────────────────────────────────────────────────────────────
// Mobile app sends: ?_t=SHEBA_APP_2026  OR  X-API-Key header  OR session cookie
$_appToken     = $_GET['_t']                    ?? '';
$_apiKeyHeader = $_SERVER['HTTP_X_API_KEY']     ?? '';
$_userAgent    = $_SERVER['HTTP_USER_AGENT']    ?? '';
$_settings     = getSettings();
$_validKey     = $_settings['kitchen_api_key']  ?? '';

$useApiKey = false;

// Check 1: URL token (most reliable — cannot be blocked by any proxy or Android HTTP quirk)
if ($_appToken === 'SHEBA_APP_2026') {
    $useApiKey = true;
}

// Check 2: X-API-Key header
if (!$useApiKey) {
    if ($_apiKeyHeader === 'SHEBA_APP_2026' ||
        (!empty($_validKey) && !empty($_apiKeyHeader) && hash_equals($_validKey, $_apiKeyHeader))) {
        $useApiKey = true;
    }
}

// Check 3: ShebaApp User-Agent (fallback)
if (!$useApiKey) {
    if (stripos($_userAgent, 'ShebaApp') !== false ||
        stripos($_userAgent, 'okhttp')   !== false ||
        stripos($_userAgent, 'Dart')     !== false) {
        $useApiKey = true;
    }
}

if (!$useApiKey) {
    requireAuth(['chef', 'juice_bar', 'kitchen', 'admin', 'waiter', 'cashier']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'pending';

// For session auth, use current user. For API Key, use all kitchen users.
if (!$useApiKey) {
    $user = getCurrentUser();
}

session_write_close();

// ─── GET: Pending Jobs ───────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'pending') {

    // ── Primary smart filter: since_id ────────────────────────────────────────
    // The app sends the last printed queue_id. Server returns ONLY jobs with id > since_id.
    // This is timezone-independent and calendar-independent (no Hijri/Gregorian issue).
    // On first run the app calls ?action=get_max_id to initialize this value.
    $sinceId = (int)($_GET['since_id'] ?? 0);

    // ── Optional time filter: since ───────────────────────────────────────────
    // Used by local_print_worker.php (Windows). If provided, adds a time filter too.
    // Safety fallback: if no filters provided, never return jobs older than 2 hours.
    $sinceRaw = $_GET['since'] ?? null;
    $sinceTime = null;
    if ($sinceRaw) {
        $sinceTs  = strtotime($sinceRaw);
        $sinceTime = $sinceTs ? date('Y-m-d H:i:s', $sinceTs) : null;
    }
    // If no filters at all, default to last 2 hours as safety net
    $maxAge = date('Y-m-d H:i:s', strtotime('-2 hours'));
    if ($sinceId === 0 && !$sinceTime) {
        $sinceTime = $maxAge;
    }

    if ($useApiKey) {
        // Build query based on which filters are active
        if ($sinceId > 0 && $sinceTime) {
            $sql    = "SELECT pq.id, pq.order_id, pq.station_user_id FROM print_queue pq
                        WHERE pq.printed_at IS NULL AND pq.id > ? AND pq.created_at >= ?
                        ORDER BY pq.id ASC LIMIT 20";
            $params = [$sinceId, $sinceTime];
        } elseif ($sinceId > 0) {
            $sql    = "SELECT pq.id, pq.order_id, pq.station_user_id FROM print_queue pq
                        WHERE pq.printed_at IS NULL AND pq.id > ?
                        ORDER BY pq.id ASC LIMIT 20";
            $params = [$sinceId];
        } else {
            $sql    = "SELECT pq.id, pq.order_id, pq.station_user_id FROM print_queue pq
                        WHERE pq.printed_at IS NULL AND pq.created_at >= ?
                        ORDER BY pq.id ASC LIMIT 20";
            $params = [$sinceTime];
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        // Browser session: always filter by current user + since_id if provided
        if ($sinceId > 0) {
            $stmt = $db->prepare("SELECT pq.id, pq.order_id, pq.station_user_id FROM print_queue pq
                WHERE pq.station_user_id = ? AND pq.printed_at IS NULL AND pq.id > ?
                ORDER BY pq.id ASC LIMIT 10");
            $stmt->execute([$user['id'], $sinceId]);
        } else {
            $stmt = $db->prepare("SELECT pq.id, pq.order_id, pq.station_user_id FROM print_queue pq
                WHERE pq.station_user_id = ? AND pq.printed_at IS NULL AND pq.created_at >= ?
                ORDER BY pq.id ASC LIMIT 10");
            $stmt->execute([$user['id'], $sinceTime]);
        }
    }
    $jobs = $stmt->fetchAll();

    // ── Enrich each job with the station user's category_ids and print_type ──
    // The Flutter app uses category_ids to filter items (show only chef's items).
    // station_print_type tells the app which ticket format to use (chef vs receipt).
    $stationCache = []; // cache user data per station_user_id
    foreach ($jobs as &$job) {
        $suid = (int)($job['station_user_id'] ?? 0);
        if ($suid > 0) {
            if (!isset($stationCache[$suid])) {
                // Fetch category permissions
                $catStmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id=?");
                $catStmt->execute([$suid]);
                $catIds = array_map('intval', array_column($catStmt->fetchAll(), 'category_id'));

                // Fetch user's printer_mac and role
                $uStmt = $db->prepare("SELECT printer_mac, role_id FROM users WHERE id=?");
                $uStmt->execute([$suid]);
                $uRow = $uStmt->fetch();

                $stationCache[$suid] = [
                    'category_ids'       => $catIds,
                    'station_printer_mac'=> $uRow['printer_mac'] ?? null,
                ];
            }
            $job['category_ids']        = $stationCache[$suid]['category_ids'];
            $job['station_printer_mac'] = $stationCache[$suid]['station_printer_mac'];
        } else {
            $job['category_ids']        = [];
            $job['station_printer_mac'] = null;
        }
    }
    unset($job);

    jsonResponse(true, $jobs);
}

// ─── GET: Get Max Queue ID + Server Time (for first-run initialization) ──────────
// Called ONCE when the app starts for the first time (last_printed_queue_id == 0).
// Returns:
//   max_id      → the highest queue ID currently in the table (skip anything <= this)
//   server_time → the server's current datetime from MySQL NOW()
// Using server_time means NO timezone issues, NO Hijri/Gregorian difference,
// NO device clock drift — all timing is based purely on the database clock.
if ($method === 'GET' && $action === 'get_max_id') {
    $row = $db->query("SELECT COALESCE(MAX(id), 0) AS max_id, NOW() AS server_time FROM print_queue")->fetch();

    // Optional: if the app sends its station_user_id, return that user's category_ids & printer_mac
    // so the app knows which items to filter and which printer to use even before the first job arrives.
    $apiUserId = (int)($_GET['station_user_id'] ?? 0);
    $userCats  = [];
    $userPrinterMac = null;
    if ($apiUserId > 0) {
        try {
            $cStmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id=?");
            $cStmt->execute([$apiUserId]);
            $userCats = array_map('intval', array_column($cStmt->fetchAll(), 'category_id'));

            $uStmt = $db->prepare("SELECT printer_mac FROM users WHERE id=?");
            $uStmt->execute([$apiUserId]);
            $uRow = $uStmt->fetch();
            $userPrinterMac = $uRow['printer_mac'] ?? null;
        } catch (Exception $e) {}
    }

    jsonResponse(true, [
        'max_id'           => (int)($row['max_id']      ?? 0),
        'server_time'      => (string)($row['server_time'] ?? date('Y-m-d H:i:s')),
        'category_ids'     => $userCats,
        'station_printer_mac' => $userPrinterMac,
    ]);
}

// ─── POST: Mark Done ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'mark_done') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id    = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'Invalid ID', 400);

    // Fetch job to update print count on order
    $jobStmt = $db->prepare("SELECT order_id, station_user_id FROM print_queue WHERE id=?");
    $jobStmt->execute([$id]);
    $job = $jobStmt->fetch();

    if ($useApiKey) {
        // API Key can mark any job as done
        $stmt = $db->prepare("UPDATE print_queue SET printed_at=NOW() WHERE id=?");
        $stmt->execute([$id]);
    } else {
        // Session user can only mark their own jobs
        $stmt = $db->prepare("UPDATE print_queue SET printed_at=NOW() WHERE id=? AND station_user_id=?");
        $stmt->execute([$id, $user['id']]);
    }

    if ($job) {
        try {
            $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")
               ->execute([$job['order_id']]);
        } catch (Exception $e) {}
    }
    jsonResponse(true, null, 'Marked as done');

}

// ─── POST: Enqueue new print job (triggered by manual print button in browser) ─
if ($method === 'POST' && $action === 'enqueue') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $orderId = (int)($input['order_id'] ?? 0);
    if (!$orderId) jsonResponse(false, null, 'order_id مطلوب', 400);

    // Resolve the station user — for API Key we use NULL (app will print for any station)
    $stationUserId = $useApiKey ? null : ($user['id'] ?? null);

    // Check if there's already an unprinted job for this order to avoid duplicates
    $check = $db->prepare("SELECT id FROM print_queue WHERE order_id = ? AND printed_at IS NULL LIMIT 1");
    $check->execute([$orderId]);
    if ($check->fetch()) {
        // Already queued — return success silently (idempotent)
        jsonResponse(true, null, 'الطلب موجود بالفعل في قائمة الانتظار');
    }

    $stmt = $db->prepare("INSERT INTO print_queue (order_id, station_user_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$orderId, $stationUserId]);
    jsonResponse(true, ['queue_id' => (int)$db->lastInsertId()], 'تمت إضافة الطلب لقائمة الطباعة');
}

jsonResponse(false, null, 'Unknown action', 400);
