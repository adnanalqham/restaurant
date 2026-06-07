<?php
require __DIR__ . '/config/db.php';
$db = getDB();
$q = $db->query('DESCRIBE order_items');
print_r($q->fetchAll(PDO::FETCH_ASSOC));
