<?php
require_once __DIR__ . '/config/db.php';
startSession();
$user = getCurrentUser();
if ($user) {
    $redirectMap = [
        'admin'     => 'admin/',
        'waiter'    => 'waiter/',
        'cashier'   => 'cashier/',
        'chef'      => 'station/',
        'juice_bar' => 'station/',
    ];
    $dest = $redirectMap[$user['role']] ?? 'login.php';
    header("Location: " . APP_URL . "/$dest");
    exit;
}
header("Location: " . APP_URL . "/login.php");
exit;
