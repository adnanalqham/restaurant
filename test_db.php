<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("DESCRIBE inv_request_items;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
