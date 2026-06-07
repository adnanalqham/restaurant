<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

$sRow = $db->query("SELECT * FROM items WHERE id=1008")->fetch(PDO::FETCH_ASSOC);

print_r(['item_1008' => $sRow]);
