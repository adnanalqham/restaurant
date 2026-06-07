<?php
/**
 * Export Stock Log to Excel or PDF (Print view)
 */
require_once __DIR__ . '/../config/db.php';
startSession();
$currentUser = getCurrentUser();
if (!$currentUser) { http_response_code(401); die('غير مصرح'); }
$userPerms = !empty($currentUser['permissions']) ? json_decode($currentUser['permissions'], true) : [];
$hasStockPerm = ($currentUser['role'] === 'admin') || (is_array($userPerms) && in_array('stock_management', $userPerms));

if (!$hasStockPerm) { http_response_code(403); die('ليس لديك صلاحية'); }

$from   = $_GET['from'] ?? date('Y-m-d');
$to     = $_GET['to']   ?? date('Y-m-d');
$format = $_GET['format'] ?? 'excel';

$db = getDB();
$settings = getSettings();
$restName = $settings['restaurant_name'] ?? 'نظام الكاشير';

// Fetch log data
$sql = "SELECT * FROM item_stock_log WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute([$from, $to]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$periodLabel = ($from === $to) ? $from : ($from . ' — ' . $to);
$typeLabels = ['set' => 'تعيين', 'add' => 'إضافة', 'deduct' => 'خصم', 'order_deduct' => 'خصم (طلب)'];
$typeColors = ['set' => '#2563eb', 'add' => '#059669', 'deduct' => '#dc2626', 'order_deduct' => '#7c3aed'];

if ($format === 'excel') {
    $filename = 'سجل_الرصيد_' . str_replace(' — ', '_', $periodLabel) . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
    <meta charset="utf-8">
    <style>
      body { font-family: Arial; direction: rtl; }
      table { border-collapse: collapse; width: 100%; }
      td, th { border: 1px solid #ccc; padding: 8px; text-align: right; }
      .title { background: #1a3c5e; color: #fff; font-size: 14pt; font-weight: bold; text-align: center; }
      .subtitle { background: #2e6da4; color: #fff; font-size: 10pt; text-align: center; }
      .hdr { background: #2e6da4; color: #fff; font-weight: bold; text-align: center; }
      .num { text-align: center; }
    </style>
    </head>
    <body>
    <table>
      <tr><td colspan="9" class="title"><?= htmlspecialchars($restName) ?> — سجل تعديلات الرصيد</td></tr>
      <tr><td colspan="9" class="subtitle">الفترة: <?= $periodLabel ?> | إجمالي العمليات: <?= count($logs) ?></td></tr>
      <tr>
        <th class="hdr">#</th>
        <th class="hdr">الصنف</th>
        <th class="hdr">نوع العملية</th>
        <th class="hdr">قبل</th>
        <th class="hdr">التغيير</th>
        <th class="hdr">بعد</th>
        <th class="hdr">الملاحظة</th>
        <th class="hdr">بواسطة</th>
        <th class="hdr">الوقت</th>
      </tr>
      <?php foreach ($logs as $i => $r): 
          $change = (float)$r['qty_change'];
          $changeStr = $change >= 0 ? "+$change" : $change;
          $changeColor = $change >= 0 ? '#059669' : '#dc2626';
          $actionName = $typeLabels[$r['action_type']] ?? $r['action_type'];
          $actionColor = $typeColors[$r['action_type']] ?? '#666';
      ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><strong><?= htmlspecialchars($r['item_name_ar']) ?></strong></td>
        <td style="color:<?= $actionColor ?>;font-weight:bold"><?= $actionName ?></td>
        <td class="num"><?= (float)$r['qty_before'] ?></td>
        <td class="num" style="color:<?= $changeColor ?>;font-weight:bold" x:str><?= $changeStr ?></td>
        <td class="num"><strong><?= (float)$r['qty_after'] ?></strong></td>
        <td><?= htmlspecialchars($r['note'] ?: '-') ?></td>
        <td><?= htmlspecialchars($r['user_name'] ?: '-') ?></td>
        <td class="num"><?= date('Y-m-d h:i A', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </body>
    </html>
    <?php
    exit;
} elseif ($format === 'pdf') {
    // For PDF we just render a nice HTML page and trigger print()
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>سجل الرصيد - <?= $periodLabel ?></title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>
            body { background: #fff; color: #000; padding: 20px; font-family: Tahoma, Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .header h1 { margin: 0 0 10px 0; font-size: 1.5rem; }
            .header p { margin: 0; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9rem; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
            th { background: #f8f9fa; font-weight: bold; border-bottom: 2px solid #ccc; }
            tr:nth-child(even) { background: #fdfdfd; }
            .num { text-align: center; }
            @media print {
                body { padding: 0; }
                .no-print { display: none; }
                @page { margin: 1cm; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="text-align:center;margin-bottom:20px">
            <button onclick="window.print()" style="padding:10px 20px;font-size:1rem;cursor:pointer;background:#2563eb;color:#fff;border:none;border-radius:5px">🖨️ طباعة / حفظ كـ PDF</button>
        </div>
        <div class="header">
            <h1><?= htmlspecialchars($restName) ?></h1>
            <h2>سجل تعديلات الرصيد</h2>
            <p>الفترة: <strong><?= $periodLabel ?></strong></p>
            <p>إجمالي العمليات: <strong><?= count($logs) ?></strong></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>الصنف</th>
                    <th>نوع العملية</th>
                    <th class="num">قبل</th>
                    <th class="num">التغيير</th>
                    <th class="num">بعد</th>
                    <th>الملاحظة</th>
                    <th>بواسطة</th>
                    <th class="num">الوقت</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:20px">لا توجد سجلات في هذه الفترة</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $i => $r): 
                    $change = (float)$r['qty_change'];
                    $changeStr = $change >= 0 ? "+$change" : $change;
                    $changeColor = $change >= 0 ? '#059669' : '#dc2626';
                    $actionName = $typeLabels[$r['action_type']] ?? $r['action_type'];
                    $actionColor = $typeColors[$r['action_type']] ?? '#666';
                ?>
                <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($r['item_name_ar']) ?></strong></td>
                    <td style="color:<?= $actionColor ?>;font-weight:bold"><?= $actionName ?></td>
                    <td class="num"><?= (float)$r['qty_before'] ?></td>
                    <td class="num" style="color:<?= $changeColor ?>;font-weight:bold" dir="ltr"><?= $changeStr ?></td>
                    <td class="num"><strong><?= (float)$r['qty_after'] ?></strong></td>
                    <td><?= htmlspecialchars($r['note'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($r['user_name'] ?: '-') ?></td>
                    <td class="num"><?= date('Y-m-d h:i A', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
