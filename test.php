<?php
require 'config/db.php';
$db=getDB();
$cols=$db->query('DESCRIBE inv_items')->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
