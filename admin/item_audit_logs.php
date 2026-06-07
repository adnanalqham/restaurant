<?php
require_once __DIR__ . '/_layout.php';
adminHeader('سجل مراقبة الأسعار والأصناف', 'activity_log');

$db = getDB();
$logs = $db->query("
    SELECT al.*, u.name as user_name, i.name_ar as item_name 
    FROM item_audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN items i ON al.item_id = i.id
    ORDER BY al.created_at DESC 
    LIMIT 500
")->fetchAll();

$actionLabels = [
    'create' => 'إضافة صنف جديد',
    'update' => 'تعديل بيانات',
    'delete' => 'حذف صنف'
];

$fieldLabels = [
    'price' => 'السعر',
    'name_ar' => 'الاسم العربي',
    'is_available' => 'التوفر',
    'all' => 'كافة البيانات'
];
?>

<div class="card">
    <div class="card-header">
        <h3>سجل التغييرات في المنيو (الأسعار والبيانات)</h3>
        <p class="text-muted">يظهر هنا كل من قام بتغيير سعر أو تعديل اسم صنف مع القيمة القديمة والجديدة.</p>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>التاريخ والوقت</th>
                    <th>الموظف</th>
                    <th>الصنف</th>
                    <th>نوع الإجراء</th>
                    <th>الحقل المعدل</th>
                    <th>القيمة القديمة</th>
                    <th>القيمة الجديدة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center">لا توجد سجلات تعديل بعد.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= date('Y/m/d H:i', strtotime($log['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($log['user_name']) ?></strong></td>
                        <td><?= htmlspecialchars($log['item_name'] ?: 'صنف محذوف') ?></td>
                        <td>
                            <span class="badge badge-<?= $log['action_type'] === 'create' ? 'success' : ($log['action_type'] === 'delete' ? 'danger' : 'info') ?>">
                                <?= $actionLabels[$log['action_type']] ?>
                            </span>
                        </td>
                        <td><?= $fieldLabels[$log['field_name']] ?? $log['field_name'] ?></td>
                        <td class="text-danger"><?= htmlspecialchars($log['old_value']) ?></td>
                        <td class="text-success"><strong><?= htmlspecialchars($log['new_value']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge-info { background: #3498db; color: #fff; }
.badge-success { background: #27ae60; color: #fff; }
.badge-danger { background: #e74c3c; color: #fff; }
.text-danger { color: #e74c3c; }
.text-success { color: #27ae60; }
</style>

<?php adminFooter(); ?>
