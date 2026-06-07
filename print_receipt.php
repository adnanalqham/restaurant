<?php
/**
 * print_receipt.php
 * ?copy=cashier  → customer receipt + waiter copy (auto-prints → chashier)
 * ?copy=kitchen  → kitchen ticket only (shows Print button, NO auto-print)
 */
require_once __DIR__ . '/config/db.php';
requireAuth();

$orderId = (int)($_GET['order_id'] ?? 0);
$copy    = $_GET['copy'] ?? 'cashier'; // cashier | kitchen
if (!$orderId) die('Invalid order');

$user = getCurrentUser();
$db   = getDB();

// ── Order ─────────────────────────────────────────────────────────────────────
$oStmt = $db->prepare("
    SELECT o.*, 
           w.name_en AS w_en, w.name AS w_ar,
           c.name_en AS c_en, c.name AS c_ar
    FROM orders o
    LEFT JOIN users w ON o.waiter_id = w.id
    LEFT JOIN users c ON o.cashier_id = c.id
    WHERE o.id = ?
");
$oStmt->execute([$orderId]);
$order = $oStmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die('Order not found');

// Increment print counts
try {
    if ($copy === 'kitchen') {
        $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")->execute([$orderId]);
        $order['kitchen_print_count'] = ($order['kitchen_print_count'] ?? 0) + 1;
    } else {
        $db->prepare("UPDATE orders SET print_count = print_count + 1 WHERE id = ?")->execute([$orderId]);
        $order['print_count'] = ($order['print_count'] ?? 0) + 1;
    }
} catch (Exception $e) {
    // Ignore error if column doesn't exist yet
}

// ── Items ─────────────────────────────────────────────────────────────────────
$iStmt = $db->prepare("
    SELECT item_name_en AS name_en, quantity AS qty,
           unit_price AS price, subtotal AS total, notes
    FROM order_items
    WHERE order_id = ? AND status != 'rejected'
    ORDER BY id ASC
");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = getSettings();
$restName = trim(preg_replace('/[^\x20-\x7E]/', '', $settings['restaurant_name'] ?? '')) ?: 'Restaurant';

// Totals
$discount = (float)($order['manual_discount'] ?? 0);
$subtotal = (float)$order['total'] + $discount;
$netTotal = (float)$order['total'] - (float)($order['refund_amount'] ?? 0);

// Waiter / Direct Staff (Supports Arabic fully)
// Priority: direct_name (set by cashier) → waiter from users join
$directName = trim($order['direct_name'] ?? '');
$waiterFromUser = trim($order['w_ar'] ?: ($order['w_en'] ?? ''));
$waiter = $directName ?: $waiterFromUser;

// Cashier name (Supports Arabic fully)
$cashierFromUser = trim($order['c_ar'] ?: ($order['c_en'] ?? ''));
$cashier = $cashierFromUser;

$dt     = date('Y-m-d H:i', strtotime($order['created_at']));
$pm     = strtoupper(trim(preg_replace('/[^\x20-\x7E]/', '', $order['payment_method'] ?? '')));

// ── Fixed-width helpers ───────────────────────────────────────────────────────
const W = 32;

function rLine(string $label, string $value, int $w = W): string {
    $pad = max(1, $w - strlen($label) - strlen($value));
    return $label . str_repeat(' ', $pad) . $value;
}
function cLine(string $text, int $w = W): string {
    $text = substr($text, 0, $w);
    $pad  = max(0, intval(($w - strlen($text)) / 2));
    return str_repeat(' ', $pad) . $text;
}
function itemLines(array $item, int $w = W): array {
    $name  = trim(preg_replace('/[^\x20-\x7E]/', '', $item['name_en'] ?? ''));
    if (!$name) $name = 'Item';
    if (strlen($name) > $w) $name = substr($name, 0, $w - 1) . '.';

    $total = number_format((float)$item['total'], 2);
    $price = number_format((float)$item['price'], 2);
    $qty   = (int)$item['qty'];

    $namePad = max(1, $w - strlen($name) - strlen($total));
    $l1 = $name . str_repeat(' ', $namePad) . $total;

    $qStr = 'x' . $qty . ' @ ' . $price;
    $l2   = str_repeat(' ', max(0, $w - strlen($qStr))) . $qStr;

    $lines = [$l1, $l2];
    if (!empty($item['notes'])) {
        $note = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
        if ($note) $lines[] = '  * ' . substr($note, 0, $w - 5);
    }
    return $lines;
}

// ── Build receipt lines ───────────────────────────────────────────────────────
function buildReceipt(string $restName, array $order, array $items,
                      float $discount, float $subtotal, float $netTotal,
                      string $waiter, string $cashier, string $dt, string $pm): array
{
    $W   = 32;
    $SEP = str_repeat('=', $W);
    $DIV = str_repeat('-', $W);

    $lines = [];
    $lines[] = ['text' => cLine($restName, $W), 'bold' => true, 'big' => true];
    $lines[] = ['text' => cLine('SALES RECEIPT', $W), 'bold' => true];
    $lines[] = ['text' => $SEP];
    
    // Large Table Number
    $lines[] = ['text' => cLine('TABLE: ' . ($order['table_number'] ?: 'Takeaway'), $W), 'bold' => true, 'big' => true];
    $lines[] = ['text' => $DIV];
    
    $lines[] = ['text' => rLine('Order No.', '#' . $order['order_number'], $W)];
    if ($waiter)
        $lines[] = ['text' => rLine('Waiter   ', $waiter, $W)];
    if ($cashier)
        $lines[] = ['text' => rLine('Cashier  ', $cashier, $W)];
    $lines[] = ['text' => rLine('Date     ', $dt, $W)];
    if (!empty($order['notes'])) {
        $n = trim(preg_replace('/[^\x20-\x7E]/', '', $order['notes']));
        if ($n) $lines[] = ['text' => rLine('Note     ', substr($n, 0, 14), $W)];
    }
    $lines[] = ['text' => $DIV];

    foreach ($items as $item) {
        foreach (itemLines($item, $W) as $l)
            $lines[] = ['text' => $l];
    }

    $lines[] = ['text' => $DIV];
    if ($discount > 0) {
        $lines[] = ['text' => rLine('Subtotal', number_format($subtotal, 2), $W)];
        $lines[] = ['text' => rLine('Discount', '-' . number_format($discount, 2), $W)];
        $lines[] = ['text' => $DIV];
    }
    if ($pm)
        $lines[] = ['text' => rLine('Payment ', $pm, $W)];
    $lines[] = ['text' => $SEP];
    $lines[] = ['text' => rLine('TOTAL', number_format($netTotal, 2) . ' YER', $W), 'bold' => true];
    $lines[] = ['text' => $SEP];
    $lines[] = ['text' => cLine('Thank you for visiting!', $W)];

    return $lines;
}

// ── Build kitchen lines ───────────────────────────────────────────────────────
function buildKitchen(string $restName, array $order, array $items,
                      string $waiter, string $cashier, string $dt): array
{
    $W   = 32;
    $SEP = str_repeat('=', $W);
    $DIV = str_repeat('-', $W);

    $lines = [];
    $lines[] = ['text' => cLine($restName, $W), 'bold' => true];
    $lines[] = ['text' => cLine('KITCHEN / WAITER COPY', $W), 'bold' => true];
    $lines[] = ['text' => $SEP];
    
    // Large Table Number
    $lines[] = ['text' => cLine('TABLE: ' . ($order['table_number'] ?: 'Takeaway'), $W), 'bold' => true, 'big' => true];
    $lines[] = ['text' => $DIV];
    
    $lines[] = ['text' => rLine('Order No.', '#' . $order['order_number'], $W)];
    if ($waiter)
        $lines[] = ['text' => rLine('Waiter   ', $waiter, $W)];
    if ($cashier)
        $lines[] = ['text' => rLine('Cashier  ', $cashier, $W)];
    $lines[] = ['text' => rLine('Time     ', $dt, $W)];
    $lines[] = ['text' => $SEP];

    foreach ($items as $item) {
        $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['name_en'] ?? ''));
        if (!$name) $name = 'Item';
        $qty  = (int)$item['qty'];

        $qStr = 'x' . $qty;
        $pad  = max(1, $W - strlen($qStr) - strlen($name));
        $lines[] = ['text' => $qStr . str_repeat(' ', $pad) . $name, 'bold' => true];

        if (!empty($item['notes'])) {
            $note = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
            if ($note) $lines[] = ['text' => '  ** ' . substr($note, 0, $W - 7) . ' **'];
        }
        $lines[] = ['text' => str_repeat('-', $W)];
    }

    $lines[] = ['text' => $SEP];
    $lines[] = ['text' => cLine('** HANDLE WITH CARE **', $W)];

    return $lines;
}

$receiptLines = buildReceipt($restName, $order, $items, $discount, $subtotal, $netTotal, $waiter, $cashier, $dt, $pm);
$kitchenLines = buildKitchen($restName, $order, $items, $waiter, $cashier, $dt);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $copy === 'kitchen' ? 'Kitchen' : 'Receipt' ?> #<?= htmlspecialchars($order['order_number']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      background: #fff;
    }

    .receipt-block {
      width: 280px;
      margin: 0 auto;
      padding: 6px 4px;
    }

    .cut-line {
      margin: 8px 0;
      border: none;
      border-top: 1px dashed #555;
      position: relative;
      width: 280px;
      margin-left: auto;
      margin-right: auto;
    }
    .cut-line::after {
      content: '✂';
      position: absolute;
      top: -9px;
      left: 50%;
      transform: translateX(-50%);
      background: #fff;
      padding: 0 4px;
      font-size: 13px;
      color: #555;
    }

    pre {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      white-space: pre;
      line-height: 1.45;
      color: #000;
    }
    pre.bold { font-weight: bold; }
    pre.big  { font-size: 14px; font-weight: bold; }

    /* Kitchen label bar — screen only */
    .kitchen-bar {
      background: #ea580c;
      color: #fff;
      text-align: center;
      font-weight: bold;
      font-size: 13px;
      letter-spacing: 2px;
      padding: 8px;
      margin-bottom: 8px;
    }

    .btn-bar {
      display: flex;
      justify-content: center;
      gap: 8px;
      padding: 14px;
    }
    .btn {
      padding: 8px 24px;
      font-size: 13px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: bold;
      font-family: inherit;
    }
    .btn-p { background: #1d4ed8; color: #fff; }
    .btn-c { background: #6b7280; color: #fff; }

    @media print {
      @page {
        margin: 4mm;
        /* No size set here — let the printer driver use the paper roll */
      }
      body { background: none; }
      .receipt-block { width: 100%; padding: 0; }
      .kitchen-bar, .btn-bar { display: none; }
      /* Hide the visual cut line — page-break handles the real cut */
      .cut-line { display: none; }
      /* Page break = paper cut between copies */
      .page-cut {
        page-break-before: always;
        break-before: page;
        padding-top: 2mm;
      }
    }
  </style>
</head>
<body>

<?php if ($copy === 'kitchen'): ?>

  <!-- Kitchen bar (screen only) -->
  <div class="kitchen-bar">🍳 KITCHEN TICKET — MNK on 10.0.0.191</div>

  <!-- Kitchen ticket -->
  <div class="receipt-block" style="page-break-after: always; break-after: page;">
    <?php foreach ($kitchenLines as $l): ?>
      <pre class="<?= (!empty($l['big']) ? 'big' : (!empty($l['bold']) ? 'bold' : '')) ?>"><?= htmlspecialchars($l['text']) ?></pre>
    <?php endforeach; ?>
  </div>

  <div class="btn-bar no-print-cut">
    <button class="btn btn-p" onclick="window.print()">🖨️ طباعة المطبخ</button>
    <button class="btn btn-c" onclick="window.close()">✕ إغلاق</button>
  </div>

  <script>
    // Highlight the print button so user knows to click it
    document.querySelector('.btn-p').focus();
  </script>

<?php else: /* CASHIER — customer receipt + cut + waiter copy — auto-prints */ ?>

  <!-- Copy 1: Customer Receipt -->
  <div class="receipt-block">
    <?php foreach ($receiptLines as $l): ?>
      <pre class="<?= (!empty($l['big']) ? 'big' : (!empty($l['bold']) ? 'bold' : '')) ?>"><?= htmlspecialchars($l['text']) ?></pre>
    <?php endforeach; ?>
  </div>

  <pre style="line-height:1.45"> </pre>
  <pre style="line-height:1.45"> </pre>
  <pre style="line-height:1.45"> </pre>
  <!-- Visual cut line (screen only) -->
  <hr class="cut-line">

  <!-- Copy 2: Waiter copy — page-break forces a cut before this section -->
  <div class="receipt-block page-cut">
    <?php foreach ($kitchenLines as $l): ?>
      <pre class="<?= (!empty($l['big']) ? 'big' : (!empty($l['bold']) ? 'bold' : '')) ?>"><?= htmlspecialchars($l['text']) ?></pre>
    <?php endforeach; ?>
  </div>

  <div class="btn-bar no-print-cut">
    <button class="btn btn-p" onclick="window.print()">🖨️ إعادة طباعة / للمطبخ</button>
    <button class="btn btn-c" onclick="window.close()">✕ إغلاق</button>
  </div>

  <script>
    // Auto-print on load but DO NOT auto-close, so user can press the buttons
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.print();
        // window.onafterprint is intentionally omitted so window stays open
      }, 700);
    });
  </script>

<?php endif; ?>

</body>
</html>