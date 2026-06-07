<?php
require_once __DIR__ . '/config/db.php';
$settings = getSettings();
print_r($settings['enable_stock_tracking'] ?? 'not set');
