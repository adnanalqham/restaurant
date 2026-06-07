<?php
/**
 * api/app_diagnostic.php
 * Called by Sheba Print app to verify connectivity + auth.
 * Returns: pending count, auth status, API key configured?
 * NO AUTH REQUIRED — intentionally public for diagnostic.
 */
require_once __DIR__ . '/../config/db.php';

$apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
$settings     = getSettings();
$configuredKey = $settings['kitchen_api_key'] ?? '';

$authOk = !empty($configuredKey) && !empty($apiKeyHeader) && hash_equals($configuredKey, $apiKeyHeader);

// Count pending jobs
$pendingCount = 0;
try {
    $db = getDB();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM print_queue WHERE printed_at IS NULL")->fetchColumn();
} catch (Exception $e) {}

jsonResponse(true, [
    'server'           => 'online',
    'api_key_configured' => !empty($configuredKey),
    'api_key_received'   => !empty($apiKeyHeader),
    'auth_ok'            => $authOk,
    'pending_jobs'       => $pendingCount,
    'configured_key_hint'=> !empty($configuredKey) ? substr($configuredKey, 0, 4) . '****' : 'NOT SET',
    'received_key_hint'  => !empty($apiKeyHeader)  ? substr($apiKeyHeader, 0, 4)  . '****' : 'NONE',
    'time'               => date('Y-m-d H:i:s'),
]);
