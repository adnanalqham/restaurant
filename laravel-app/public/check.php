<?php
$db = new PDO('mysql:host=127.0.0.1;dbname=restaurant_pos;charset=utf8mb4', 'root', '771603365');
header('Content-Type: text/plain; charset=utf-8');

echo "=== INGREDIENTS COLUMNS ===\n";
foreach ($db->query("DESCRIBE ingredients")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']} | {$row['Type']}\n";
}
