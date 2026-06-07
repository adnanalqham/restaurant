<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$stmt = $db->query("SELECT u.username, u.role_id, r.name as role_name, u.warehouse_id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = 'mgde'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
