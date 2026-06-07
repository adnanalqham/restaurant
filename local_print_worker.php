<?php
/**
 * Sheba Restaurant Local Polling Print Worker
 * Runs on the Windows machine connected to the printers.
 * Polls the PHP backend (local or cloud) every 5 seconds for pending print jobs,
 * fetches ESC/POS data, prints via PowerShell, and marks jobs as done.
 */

// Disable execution time limit for CLI
set_time_limit(0);

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURATION - EDIT THIS IF HOSTING THE SYSTEM ONLINE
// ─────────────────────────────────────────────────────────────────────────────
$baseUrl = 'https://shebahotel.com/restaurant0/'; // ضع رابط موقعك المرفوع هنا
$token   = 'SHEBA_APP_2026';
$pollInterval = 5; // Seconds between checks
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// SESSION START: Record the time this worker was started.
// We will ONLY fetch print jobs created AT OR AFTER this moment.
// This guarantees old unprinted jobs from the database are never printed.
// ─────────────────────────────────────────────────────────────────────────────
$sessionStart = date('Y-m-d H:i:s'); // Timestamp of when this worker session started
$printedQueueIds = []; // Track already-printed queue IDs to prevent duplicates in same session

// We need rawPrint function. We can require it directly as it resides in api/print_direct_lib.php.
// If the worker is running from the root folder, the relative path is:
$libPath = __DIR__ . '/api/print_direct_lib.php';

if (!file_exists($libPath)) {
    die("Error: print_direct_lib.php not found at: $libPath\n");
}
require_once $libPath;

// CLI output helper
function logMessage($msg) {
    $time = date('Y-m-d H:i:s');
    echo "[$time] $msg\n";
}

logMessage("==============================================");
logMessage("   Sheba POS Auto Print Worker Started");
logMessage("   Target URL: $baseUrl");
logMessage("   Session started at: $sessionStart");
logMessage("   Only orders created AFTER this time will be printed.");
logMessage("   Checking every $pollInterval seconds...");
logMessage("==============================================");

while (true) {
    // 1. Fetch pending jobs (only jobs created AFTER session start, to skip old queued jobs)
    $pendingUrl = rtrim($baseUrl, '/') . '/api/print_queue.php?action=pending&_t=' . urlencode($token)
                . '&since=' . urlencode($sessionStart);
    
    $ch = curl_init($pendingUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL errors for local dev/self-signed certs
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        logMessage("Network Error: Could not reach POS server ($error)");
        sleep($pollInterval);
        continue;
    }

    if ($httpCode !== 200) {
        logMessage("HTTP Error $httpCode from server. Response: " . substr(trim($response), 0, 100));
        sleep($pollInterval);
        continue;
    }

    $resData = json_decode($response, true);
    if (!isset($resData['success']) || !$resData['success']) {
        logMessage("Server returned failure: " . ($resData['message'] ?? 'Unknown error'));
        sleep($pollInterval);
        continue;
    }

    $jobs = $resData['data'] ?? [];
    if (!empty($jobs)) {
        logMessage("Found " . count($jobs) . " pending print job(s)");

        foreach ($jobs as $job) {
            $queueId = $job['id'];
            $orderId = $job['order_id'];
            $stationUserId = $job['station_user_id'] ?? 0;

            // Skip if already printed in this session (duplicate protection)
            if (in_array($queueId, $printedQueueIds)) {
                logMessage("Skipping Job #$queueId — already printed in this session.");
                continue;
            }

            logMessage("Processing Job #$queueId (Order #$orderId, Station: " . ($stationUserId ?: 'General') . ")...");

            // 2. Fetch ESC/POS bytes from print_direct.php
            $escUrl = rtrim($baseUrl, '/') . '/api/print_direct.php?action=get_esc&order_id=' . $orderId 
                    . '&station_user_id=' . $stationUserId . '&_t=' . urlencode($token);
            
            $ch = curl_init($escUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $escResponse = curl_exec($ch);
            curl_close($ch);

            if ($escResponse === false) {
                logMessage("Error: Could not retrieve ESC/POS data for order #$orderId");
                continue;
            }

            $escData = json_decode($escResponse, true);
            if (!isset($escData['success']) || !$escData['success']) {
                logMessage("Error: Server failed to generate ESC/POS: " . ($escData['message'] ?? 'Unknown error'));
                continue;
            }

            $printerName = $escData['data']['printer_name'] ?? '';
            $base64Esc = $escData['data']['esc_pos_base64'] ?? '';

            if (empty($base64Esc)) {
                logMessage("Error: Empty print payload for order #$orderId");
                continue;
            }

            $rawBytes = base64_decode($base64Esc);
            if ($rawBytes === false) {
                logMessage("Error: Failed to decode Base64 data for order #$orderId");
                continue;
            }

            if (empty($printerName)) {
                logMessage("Warning: No printer name resolved. Defaulting to 'MNK on 10.0.0.191'.");
                $printerName = 'MNK on 10.0.0.191';
            }

            logMessage("Sending ESC/POS print job to printer: '$printerName'...");
            
            // 3. Print locally via rawPrint
            $printResult = rawPrint($printerName, $rawBytes);

            if ($printResult['ok']) {
                logMessage("Success: Print job completed for Order #$orderId on '$printerName'");

                // Track this queue ID to prevent duplicates in same session
                $printedQueueIds[] = $queueId;
                // Keep the array from growing too large (keep last 500 IDs only)
                if (count($printedQueueIds) > 500) {
                    $printedQueueIds = array_slice($printedQueueIds, -500);
                }

                // 4. Mark job as done in queue
                $markUrl = rtrim($baseUrl, '/') . '/api/print_queue.php?action=mark_done&_t=' . urlencode($token);
                $markPayload = json_encode(['id' => $queueId]);
                
                $ch = curl_init($markUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $markPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $markResponse = curl_exec($ch);
                curl_close($ch);

                if ($markResponse !== false) {
                    $markResult = json_decode($markResponse, true);
                    if (isset($markResult['success']) && $markResult['success']) {
                        logMessage("Job #$queueId marked as printed on server.");
                    } else {
                        logMessage("Warning: Failed to mark job #$queueId as done on server: " . ($markResult['message'] ?? 'Unknown'));
                    }
                } else {
                    logMessage("Warning: Failed to communicate mark_done for job #$queueId");
                }
            } else {
                logMessage("Failed to print Order #$orderId: " . $printResult['msg']);
            }
        }
    }

    sleep($pollInterval);
}
