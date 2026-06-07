<?php
require_once __DIR__ . '/config/db.php';
session_start();
// Mock a waiter user for the test
$_SESSION['user'] = ['id' => 1, 'role' => 'admin', 'name' => 'Test']; 

function test($url) {
    echo "<h3>Testing $url ...</h3>";
    try {
        // We can't easily do a sub-request, so we'll just mock the GET variables and call the functions if possible, 
        // but it's easier to just run the SQLs manually here.
        $db = getDB();
        if (strpos($url, 'users.php') !== false) {
            $sql = "SELECT u.id, u.name, u.can_print FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name='cashier'";
            $db->query($sql);
            echo "✅ Users API SQL OK<br>";
        }
        if (strpos($url, 'categories.php') !== false) {
            $sql = "SELECT * FROM categories WHERE is_active=1";
            $db->query($sql);
            echo "✅ Categories API SQL OK<br>";
        }
        if (strpos($url, 'items.php') !== false) {
            $sql = "SELECT i.* FROM items i WHERE i.is_available=1";
            $db->query($sql);
            echo "✅ Items API SQL OK<br>";
        }
        if (strpos($url, 'orders.php') !== false) {
            $sql = "SELECT o.* FROM orders o WHERE o.status='ready'";
            $db->query($sql);
            echo "✅ Orders API SQL OK<br>";
        }
    } catch (Exception $e) {
        echo "<b style='color:red'>❌ FAILED: " . $e->getMessage() . "</b><br>";
    }
}

test('api/users.php');
test('api/categories.php');
test('api/items.php');
test('api/orders.php');
