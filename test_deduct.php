<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

// Simulate deductOrderStock for order 2471
$orderId = 2471;

$stmt = $db->prepare("SELECT order_number, is_stock_deducted FROM orders WHERE id=?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
echo "Order: "; print_r($order);

if (!$order || $order['is_stock_deducted'] == 1) {
    echo "Already deducted or not found. Resetting is_stock_deducted to 0...<br>";
    $db->prepare("UPDATE orders SET is_stock_deducted=0 WHERE id=?")->execute([$orderId]);
}

// Now run the deduction manually
$iStmt = $db->prepare("SELECT item_id, quantity FROM order_items WHERE order_id=? AND status != 'rejected'");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll();
echo "Items: "; print_r($items);

$qtyPerItem = [];
foreach ($items as $item) {
    $iId = (int)$item['item_id'];
    if ($iId > 0) {
        $qtyPerItem[$iId] = ($qtyPerItem[$iId] ?? 0) + (float)$item['quantity'];
    }
}
echo "Qty per item: "; print_r($qtyPerItem);

foreach ($qtyPerItem as $stockItemId => $deductQty) {
    $sRow = $db->prepare("SELECT stock_qty FROM item_stock WHERE item_id=?");
    $sRow->execute([$stockItemId]);
    $stockRow = $sRow->fetch();
    echo "Stock row for item $stockItemId: "; print_r($stockRow);
    if (!$stockRow) { echo "NOT TRACKED - SKIPPING\n"; continue; }

    $before = (float)$stockRow['stock_qty'];
    $after  = max(0, $before - $deductQty);
    echo "Before: $before, Deduct: $deductQty, After: $after\n";

    $db->prepare("UPDATE item_stock SET stock_qty=?, updated_by=1 WHERE item_id=?")
       ->execute([$after, $stockItemId]);

    $nameRow = $db->prepare("SELECT name_ar FROM items WHERE id=?");
    $nameRow->execute([$stockItemId]);
    $itemName = $nameRow->fetchColumn() ?: 'صنف #' . $stockItemId;

    $db->prepare("INSERT INTO item_stock_log (item_id, item_name_ar, action_type, qty_before, qty_change, qty_after, note, user_id, user_name)
                  VALUES (?, ?, 'order_deduct', ?, ?, ?, ?, ?, ?)")
       ->execute([$stockItemId, $itemName, $before, -$deductQty, $after, "تأكيد طلب رقم {$order['order_number']}", 1, 'مدير النظام']);

    echo "Deducted successfully for item $stockItemId\n";
}

$db->prepare("UPDATE orders SET is_stock_deducted=1 WHERE id=?")->execute([$orderId]);

// Verify
$stockAfter = $db->query("SELECT * FROM item_stock WHERE item_id=39")->fetch(PDO::FETCH_ASSOC);
echo "Stock after: "; print_r($stockAfter);
