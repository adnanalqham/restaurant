<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>Station Debugger v1.1</h2>";

echo "✅ Debugger is running...<br>";


// 1. Check DB Connection
try {
    $db = getDB();
    echo "✅ Database connection: OK<br>";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// 2. Check Permissions
$stmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id = ?");
$stmt->execute([$user['id']]);
$cats = array_column($stmt->fetchAll(), 'category_id');
echo "✅ Category Permissions: " . (empty($cats) ? "ALL (Access everything)" : "Restricted to ID(s): " . implode(', ', $cats)) . "<br>";

// 3. Test SQL Query used by the station
echo "<h3>Testing Order Fetch (Simulating Kitchen View)...</h3>";
$sql = "Unknown";
try {
    $sql = "SELECT o.*, 
                   w.name as waiter_name, 
                   c.name as cashier_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN users w ON o.waiter_id = w.id
            LEFT JOIN users c ON o.cashier_id = c.id
            WHERE 1=1";
            
    $isKitchen = in_array($user['role'], ['chef', 'juice_bar', 'kitchen']);
    $params = [];
    if ($isKitchen && !empty($cats)) {
        $catMarks = implode(',', array_fill(0, count($cats), '?'));
        $sql .= " AND EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.category_id IN ($catMarks))";
        $params = array_merge($params, array_map('intval', $cats));
    }
    
    $sql .= " ORDER BY o.created_at DESC LIMIT 5";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    echo "✅ SQL Query executed successfully.<br>";
    echo "Found " . count($results) . " recent orders.<br>";
    
    if (count($results) > 0) {
        echo "<pre style='background:#f4f4f4; padding:10px; border-radius:5px; border:1px solid #ddd; max-height: 200px; overflow: auto;'>";
        echo "Sample data found:\n";
        print_r($results[0]);
        echo "</pre>";
    } else {
        echo "⚠️ No orders found matching the filter.<br>";
    }

} catch (Exception $e) {
    echo "<div style='color:red; background:#fee; padding:15px; border-radius:8px; border:1px solid red; margin-top:10px'>";
    echo "<b>❌ SQL Error detected:</b> " . $e->getMessage();
    echo "<br><br><b>The Query attempted:</b><br><code style='background:#fdd; padding:4px'>" . $sql . "</code>";
    echo "</div>";
}

echo "<hr><p><b>Next Step:</b> If all tests above are GREEN, the server is fine. The issue might be in your Browser Cache. Please press <b>Ctrl + Shift + R</b> (Hard Refresh) to clear the cache and try again.</p>";
