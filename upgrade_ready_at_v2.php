<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>Starting Database Update for Ready Time tracking...</h2>";

try {
    $db = getDB();
    
    // 1. Add ready_at column
    echo "Processing 'orders' table...<br>";
    try {
        $db->exec("ALTER TABLE orders ADD COLUMN ready_at TIMESTAMP NULL DEFAULT NULL AFTER paid_at");
        echo "✅ Created 'ready_at' column.<br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ 'ready_at' column already exists.<br>";
        } else {
            echo "⚠️ Error adding column: " . $e->getMessage() . "<br>";
        }
    }

    // 2. Fix existing ready orders
    $sql = "UPDATE orders SET ready_at = created_at WHERE ready_at IS NULL AND (status IN ('ready', 'delivered', 'paid', 'completed'))";
    $count = $db->exec($sql);
    echo "✅ Fixed $count existing orders with missing ready time.<br>";

    echo "<br><h3 style='color:green'>Database update complete! The Ready Time system is active.</h3>";
    echo "<a href='index.php'>Return to Dashboard</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Critical Error: " . $e->getMessage() . "</h3>";
}
