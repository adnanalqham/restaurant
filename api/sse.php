<?php
require_once __DIR__ . '/../config/db.php';
startSession();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
// Disable output buffering
if (function_exists('ob_end_clean')) {
    while (ob_get_level()) ob_end_clean();
}
ob_implicit_flush(1);

$user = getCurrentUser();
if (!$user) {
    echo "event: error\ndata: {\"message\":\"Unauthorized\"}\n\n";
    flush();
    exit;
}
// RELEASE SESSION LOCK! 
// This allows other pages to be opened while SSE is running.
session_write_close(); 

$db = getDB();
$lastId = (int)($_GET['last_id'] ?? 0);

// Clean old events (older than 5 minutes)
try {
    $db->exec("DELETE FROM sse_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
} catch (Exception $e) {}

// Send initial 4KB padding to bypass proxy buffers (Nginx, LiteSpeed, etc)
echo ":" . str_repeat(" ", 4096) . "\n\n";

// Send initial ping
echo "event: ping\ndata: {\"time\":\"" . date('Y-m-d H:i:s') . "\",\"user\":\"" . addslashes($user['name'] ?? 'Guest') . "\"}\n\n";
flush();

$timeout = 0;
$maxTimeout = 30; // seconds

while ($timeout < $maxTimeout) {
    $stmt = $db->prepare("
        SELECT * FROM sse_events 
        WHERE id > ? 
        AND (target_roles IS NULL OR target_roles = '' OR FIND_IN_SET(?, target_roles))
        ORDER BY id ASC 
        LIMIT 20
    ");
    $stmt->execute([$lastId, $user['role']]);
    $events = $stmt->fetchAll();

    foreach ($events as $event) {
        $lastId = $event['id'];
        $payload = $event['payload'];

        // Filter station events by user_id if applicable
        if (in_array($event['event_type'], ['station_order'])) {
            $decoded = json_decode($payload, true);
            if (isset($decoded['target_user']) && $decoded['target_user'] != $user['id'] && $user['role'] !== 'admin') {
                continue;
            }
        }

        echo "id: {$event['id']}\n";
        echo "event: {$event['event_type']}\n";
        echo "data: {$payload}\n\n";
    }

    if (!empty($events)) {
        flush();
        $timeout = 0; // Reset timeout if we sent data
    } else {
        // Send heartbeat comment (keeps connection alive and punches through buffers)
        echo ": heartbeat " . date('H:i:s') . "\n\n";
        flush();
    }

    if (connection_aborted()) break;
    sleep(1);
    $timeout += 1;
}

// Close and let client reconnect
echo "event: reconnect\ndata: {\"last_id\": $lastId}\n\n";
flush();
