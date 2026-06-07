<?php
$f = file_get_contents('c:/xampp3/htdocs/restaurant/api/orders.php');

$old = '// On confirm or paid: push to stations' . "\r\n" .
'    if ($status === \'confirmed\' || $status === \'paid\') {' . "\r\n" .
'        $settings = getSettings();' . "\r\n" .
'        if (($settings[\'enable_stock_tracking\'] ?? \'0\') === \'1\') {' . "\r\n" .
'            deductOrderStock($db, $id, $user);' . "\r\n" .
'        }' . "\r\n" .
'        distributeOrderToStations($db, $id, $status);' . "\r\n" .
'        // Auto-print Receipt' . "\r\n" .
'        triggerPrint(\'receipt\', $id);' . "\r\n" .
'    }' . "\r\n" .
'    ' . "\r\n" .
'    // On cancel: restore stock if deducted' . "\r\n" .
'    if ($status === \'cancelled\') {' . "\r\n" .
'        $settings = getSettings();' . "\r\n" .
'        if (($settings[\'enable_stock_tracking\'] ?? \'0\') === \'1\') {' . "\r\n" .
'            restoreOrderStock($db, $id, $user);' . "\r\n" .
'        }' . "\r\n" .
'    }';

$new = '// On confirm or paid: push to stations' . "\r\n" .
'    if ($status === \'confirmed\' || $status === \'paid\') {' . "\r\n" .
'        // Direct DB read to bypass static cache in getSettings()' . "\r\n" .
'        $stStmt = $db->prepare("SELECT `value` FROM settings WHERE `key`=\'enable_stock_tracking\'");' . "\r\n" .
'        $stStmt->execute();' . "\r\n" .
'        if ($stStmt->fetchColumn() === \'1\') {' . "\r\n" .
'            deductOrderStock($db, $id, $user);' . "\r\n" .
'        }' . "\r\n" .
'        distributeOrderToStations($db, $id, $status);' . "\r\n" .
'        // Auto-print Receipt' . "\r\n" .
'        triggerPrint(\'receipt\', $id);' . "\r\n" .
'    }' . "\r\n" .
'    ' . "\r\n" .
'    // On cancel: restore stock if deducted' . "\r\n" .
'    if ($status === \'cancelled\') {' . "\r\n" .
'        $stStmt2 = $db->prepare("SELECT `value` FROM settings WHERE `key`=\'enable_stock_tracking\'");' . "\r\n" .
'        $stStmt2->execute();' . "\r\n" .
'        if ($stStmt2->fetchColumn() === \'1\') {' . "\r\n" .
'            restoreOrderStock($db, $id, $user);' . "\r\n" .
'        }' . "\r\n" .
'    }';

$count = 0;
$result = str_replace($old, $new, $f, $count);
if ($count > 0) {
    file_put_contents('c:/xampp3/htdocs/restaurant/api/orders.php', $result);
    echo "Replaced $count occurrence(s). ✅";
} else {
    echo "Pattern NOT found! Length old: " . strlen($old);
    // Debug: find where pattern starts
    $pos = strpos($f, '// On confirm or paid: push to stations');
    echo " | Found at: $pos";
    echo "\n\nActual content at that position:\n";
    echo substr($f, $pos, 600);
}
