<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة المخازن', 'warehouses');
?>

<div class="card">
    <div class="card-header flex justify-between align-center">
        <h3>قائمة المخازن</h3>
        <button class="btn btn-primary" onclick="openWarehouseModal()"><i class="fas fa-plus"></i> إضافة مخزن</button>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>اسم المخزن</th>
                    <th>نوع المخزن</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody id="warehouses-list">
                <tr><td colspan="4" class="text-center text-muted">جاري التحميل...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Warehouse Modal -->
<div class="modal-backdrop hidden" id="warehouse-modal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3 id="modal-title">إضافة مخزن</h3>
            <button class="modal-close" onclick="closeModal('warehouse-modal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="warehouse-form" onsubmit="event.preventDefault(); saveWarehouse();">
                <input type="hidden" id="wh_id">
                <div class="form-group">
                    <label>اسم المخزن</label>
                    <input type="text" id="wh_name" class="form-control" required placeholder="مثال: مخزن العصائر">
                </div>
                <div class="form-group">
                    <label>نوع المخزن</label>
                    <select id="wh_type" class="form-control">
                        <option value="sub">مخزن فرعي (نقطة بيع أو استهلاك)</option>
                        <option value="main">مخزن رئيسي (مورد للمخازن الفرعية)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-10">حفظ المخزن</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadWarehouses);

async function loadWarehouses() {
    try {
        const res = await apiCall('/api/warehouses.php?action=get_all');
        if (res.success) {
            document.getElementById('warehouses-list').innerHTML = res.data.map(w => `
                <tr>
                    <td><strong>${w.name}</strong></td>
                    <td>${w.type === 'main' ? '<span class="badge badge-success">مخزن رئيسي</span>' : '<span class="badge badge-info">مخزن فرعي</span>'}</td>
                    <td dir="ltr" style="text-align:right">${w.created_at ? new Date(w.created_at).toLocaleString('en-US', {hour12:true}) : '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick="editWarehouse('${w.id}', '${w.name}', '${w.type}')"><i class="fas fa-edit"></i></button>
                        ${w.id !== 'main' ? `<button class="btn btn-sm btn-outline text-danger" onclick="deleteWarehouse('${w.id}', '${w.name}')"><i class="fas fa-trash"></i></button>` : ''}
                    </td>
                </tr>
            `).join('');
        } else {
            alert('فشل تحميل المخازن: ' + (res.message || 'خطأ غير معروف'));
            document.getElementById('warehouses-list').innerHTML = `<tr><td colspan="4" class="text-center text-danger">${res.message}</td></tr>`;
        }
    } catch (e) {
        alert('JS Error in loadWarehouses: ' + e.message);
    }
}

function openWarehouseModal() {
    document.getElementById('modal-title').innerText = 'إضافة مخزن جديد';
    document.getElementById('warehouse-form').reset();
    document.getElementById('wh_id').value = '';
    document.getElementById('warehouse-modal').classList.remove('hidden');
}

function editWarehouse(id, name, type) {
    document.getElementById('modal-title').innerText = 'تعديل مخزن';
    document.getElementById('wh_id').value = id;
    document.getElementById('wh_name').value = name;
    document.getElementById('wh_type').value = type;
    document.getElementById('warehouse-modal').classList.remove('hidden');
}

async function saveWarehouse() {
    try {
        const fd = new FormData();
        fd.append('id', document.getElementById('wh_id').value);
        fd.append('name', document.getElementById('wh_name').value);
        fd.append('type', document.getElementById('wh_type').value);

        const res = await apiCall('/api/warehouses.php?action=save', 'POST', fd);
        
        if (res.success) {
            showToast(res.message, 'success');
            closeModal('warehouse-modal');
            loadWarehouses();
        } else {
            alert('فشل الحفظ: ' + res.message);
        }
    } catch (e) {
        alert('JS Error in saveWarehouse: ' + e.message);
    }
}

async function deleteWarehouse(id, name) {
    if (!confirmAction(`هل أنت متأكد من حذف المخزن '${name}'؟`)) return;
    const fd = new FormData();
    fd.append('id', id);
    const res = await apiCall('/api/warehouses.php?action=delete', 'POST', fd);
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) loadWarehouses();
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>

<?php adminFooter(); ?>
