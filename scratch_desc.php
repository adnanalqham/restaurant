<?php
require 'config/db.php';
$db = getDB();
echo "--- order_items --- \n";
print_r($db->query("DESCRIBE order_items")->fetchAll());
echo "\n--- orders --- \n";
print_r($db->query("DESCRIBE orders")->fetchAll());
