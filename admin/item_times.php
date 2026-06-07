<?php
require_once __DIR__ . '/_layout.php';
adminHeader('أوقات تحضير الأصناف ⏱️', 'item_times');

$db = getDB();
$filter = $_GET['filter'] ?? '7days';

// Determine date range condition
$dateCondition = "";
switch ($filter) {
    case 'today':
        $dateCondition = "AND DATE(oi.prep_end_time) = CURDATE()";
        break;
    case '7days':
        $dateCondition = "AND oi.prep_end_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $dateCondition = "AND oi.prep_end_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'all':
    default:
        $dateCondition = "";
        break;
}

// Main query: calculate prep time in seconds, group by item
$stmt = $db->query("
    SELECT 
        i.name_ar, 
        i.name_en, 
        COUNT(oi.id) as times_prepared,
        AVG(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as avg_time_sec,
        MIN(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as min_time_sec,
        MAX(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as max_time_sec
    FROM order_items oi
    JOIN items i ON oi.item_id = i.id
    WHERE oi.prep_start_time IS NOT NULL 
      AND oi.prep_end_time IS NOT NULL
      $dateCondition
    GROUP BY oi.item_id
    ORDER BY avg_time_sec DESC
");
$itemStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatSecToMin($seconds) {
    if (!$seconds || $seconds < 0) return "-";
    $m = floor($seconds / 60);
    $s = round($seconds % 60);
    if ($m > 0) {
        return sprintf("%dد و %02dث", $m, $s);
    }
    return sprintf("%d ثانية", $s);
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px; gap: 15px;">
    <div>
        <h2 style="margin: 0; font-size: 1.6rem; color: var(--text-main);">
            <i class="fas fa-stopwatch" style="color: var(--primary); margin-left: 8px;"></i>أوقات تحضير الأصناف
        </h2>
        <p style="margin: 6px 0 0 0; color: var(--text-muted); font-size: 0.95rem;">
            تتبع الوقت المستغرق من لحظة استلام الشيف للطلب (تحضير) حتى انتهائه (جاهز).
        </p>
    </div>
    <div>
        <form method="GET" style="margin: 0;">
            <select name="filter" class="form-control" onchange="this.form.submit()" style="padding: 8px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text-main); font-family: inherit; cursor: pointer; min-width: 150px;">
                <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>اليوم</option>
                <option value="7days" <?= $filter == '7days' ? 'selected' : '' ?>>آخر 7 أيام</option>
                <option value="30days" <?= $filter == '30days' ? 'selected' : '' ?>>آخر 30 يوم</option>
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>كل الأوقات</option>
            </select>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">الصنف</th>
                        <th class="text-center py-3">مرات التحضير</th>
                        <th class="text-center py-3">متوسط الوقت <i class="fas fa-clock text-primary"></i></th>
                        <th class="text-center py-3">أسرع وقت <i class="fas fa-bolt text-success"></i></th>
                        <th class="text-center py-3">أبطأ وقت <i class="fas fa-hourglass-end text-danger"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itemStats)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3 text-light"></i>
                                <h5>لا توجد بيانات للفترة المحددة</h5>
                                <p>يجب على الشيف استخدام أزرار "تحضير" و "جاهز" ليتم حساب الوقت.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($itemStats as $stat): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($stat['name_ar']) ?></td>
                                <td class="text-center"><span class="badge bg-secondary rounded-pill px-3"><?= $stat['times_prepared'] ?> مرات</span></td>
                                <td class="text-center fw-bold text-primary" style="font-size: 1.1rem;"><?= formatSecToMin($stat['avg_time_sec']) ?></td>
                                <td class="text-center text-success fw-bold"><?= formatSecToMin($stat['min_time_sec']) ?></td>
                                <td class="text-center text-danger fw-bold"><?= formatSecToMin($stat['max_time_sec']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php adminFooter(); ?>
