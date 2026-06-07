<?php
require 'config/db.php';
$db=getDB();
$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
