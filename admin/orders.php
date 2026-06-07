<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة الطلبات', 'orders');
?>
<style>
.orders-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}
.order-card {
  height: 100%;
}
</style>
<!-- Filters -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="search-box" style="position: relative; display: inline-block;">
        <span class="search-icon" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="search-input" placeholder="🔍 بحث برقم الفاتورة..." style="padding-right: 35px; border-radius: 20px; width:auto; min-width:200px" oninput="debouncedLoadOrders()">
      </div>
       <select class="form-control" id="filter-status" style="width:auto;min-width:180px">
        <option value="active">كل الطلبات النشطة</option>
        <option value="">كل الحالات</option>
        <option value="pending">معلّق</option>
        <option value="sent_to_cashier">أُرسل للكاشير</option>
        <option value="confirmed">بدأ التحضير</option>
        <option value="in_progress">قيد التحضير</option>
        <option value="ready">جاهز للاستلام</option>
        <option value="paid">مدفوع</option>
        <option value="delivered">تم التسليم</option>
        <option value="cancelled">ملغي</option>
      </select>
      <div style="display:flex;gap:8px;align-items:center">
        <label style="font-size:.85rem;margin-right:5px">من:</label>
        <input type="date" class="form-control" id="filter-from-date" style="width:auto" value="">
        <label style="font-size:.85rem;margin-right:5px">إلى:</label>
        <input type="date" class="form-control" id="filter-to-date" style="width:auto" value="">
      </div>
      <button class="btn btn-outline btn-primary btn-sm" onclick="loadOrders()"><i class="fas fa-sync-alt"></i> تحديث</button>
      <span id="orders-count" class="badge badge-info" style="font-size:.9rem"></span>
      <div style="flex-grow:1"></div>
      <label class="flex gap-8 align-center" style="cursor:pointer;font-weight:600"><input type="checkbox" id="select-all" onclick="toggleSelectAll(this)" style="width:18px;height:18px;accent-color:var(--danger)"> تحديد الكل</label>
      <button class="btn btn-success btn-sm" id="smart-deliver-btn" style="margin-right:8px;" onclick="executeSmartDeliver()"><i class="fas fa-magic"></i> تسليم ذكي (الكل)</button>
      <button class="btn btn-danger btn-sm" id="bulk-delete-btn" style="display:none; margin-right:8px;" onclick="executeBulkDelete('/api/orders.php', 'row-checkbox', 'loadOrders')"><i class="fas fa-trash"></i> حذف المحدّد (<span id="selected-count">0</span>)</button>
    </div>
  </div>
</div>

<div id="orders-grid" class="orders-grid">
  <div style="grid-column:1/-1;text-align:center;padding:60px"><div class="spinner"></div></div>
</div>
<div id="pagination-container"></div>

<!-- Order Details Modal -->
<div class="modal-backdrop hidden" id="order-detail-modal">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3><i class="fas fa-clipboard-list"></i> تفاصيل الطلب <span id="detail-order-number"></span></h3>
      <button class="modal-close" onclick="closeDetailModal()">✕</button>
    </div>
    <div class="modal-body" id="order-detail-body"></div>
    <div class="modal-footer" id="order-detail-actions"></div>
  </div>
</div>

<script>
let ordersData = [];
let currentPage = 1;
let loadOrdersTimer = null;

function debouncedLoadOrders() {
  clearTimeout(loadOrdersTimer);
  loadOrdersTimer = setTimeout(loadOrders, 300);
}

// Update deliver button state (removed because smart deliver is always available)
document.addEventListener('change', function(e) {
    // Keep this only for updating the select-all UI if needed, but bulk deliver is now smart
});

async function executeSmartDeliver() {
  if (!confirm('هل أنت متأكد من تسليم جميع الطلبات النشطة حالياً في النظام؟ (لا يقتصر على الـ 100 طلب المعروضة)')) return;

  const btn = document.getElementById('smart-deliver-btn');
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;border-top-color:#fff;display:inline-block;margin-left:5px"></div> جاري التحديث...';

  const res = await apiCall('/api/orders.php?action=deliver_all_active', 'POST', {});
  
  btn.innerHTML = originalHtml;
  btn.disabled = false;
  document.getElementById('select-all').checked = false;

  if (res && res.success) {
      showToast(res.message || 'تم تسليم الطلبات بنجاح', 'success');
      loadOrders();
  } else {
      showToast(res?.message || 'حدث خطأ أثناء التسليم', 'danger');
  }
}

async function loadOrders(deletedIds = null) {
  if (deletedIds && Array.isArray(deletedIds) && deletedIds.length) {
    ordersData = ordersData.filter(o => !deletedIds.includes(o.id.toString()));
    document.getElementById('orders-count').textContent = ordersData.length + ' طلب';
    renderOrders();
    return;
  }
  const btn = event && event.target ? event.target.closest('button') : null;
  const status = document.getElementById('filter-status').value;
  const fromDate = document.getElementById('filter-from-date').value;
  const toDate   = document.getElementById('filter-to-date').value;
  const search   = document.getElementById('search-input') ? document.getElementById('search-input').value.trim() : '';
  
  // When viewing all statuses or historical ones, use a higher limit
  const historicalStatuses = ['', 'paid', 'delivered', 'cancelled', 'refunded'];
  const limit = historicalStatuses.includes(status) ? 500 : 100;
  
  let url = '/api/orders.php?limit=' + limit;
  if (status) url += '&status=' + status;
  if (fromDate) url += '&from_date=' + fromDate;
  if (toDate) url += '&to_date=' + toDate;
  if (search) url += '&search=' + encodeURIComponent(search);

  const res = await apiCall(url);
  if (!res.success) return;
  ordersData = res.data;
  document.getElementById('orders-count').textContent = ordersData.length + ' طلب';
  renderOrders();
}

function renderOrders() {
  const grid = document.getElementById('orders-grid');
  
  if (!ordersData.length) {
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><span class="icon"><i class="fas fa-clipboard-list"></i></span><h3>لا توجد طلبات</h3></div>';
    document.getElementById('pagination-container').innerHTML = '';
    return;
  }

  const limit = 10;
  const paginatedOrders = paginateData(ordersData, currentPage, limit);

  // Uncheck select-all when page changes
  const selectAllBtn = document.getElementById('select-all');
  if (selectAllBtn) selectAllBtn.checked = false;
  updateBulkDeleteBtn();

  grid.innerHTML = paginatedOrders.map(o => `
    <div class="order-card" id="order-card-${o.id}" style="background: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; border-top: 4px solid var(--primary); display: flex; flex-direction: column; min-height: 200px; margin-bottom: 20px;">
      <div class="order-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 12px;">
        <div style="display:flex; align-items:center; gap:6px">
          <label style="display:flex;align-items:center;cursor:pointer"><input type="checkbox" class="row-checkbox" value="${o.id}" onclick="updateBulkDeleteBtn()" style="width:18px;height:18px;accent-color:var(--danger);margin-left:8px"></label>
          <strong style="color: var(--primary); font-size: 1.05rem">#${o.order_number}</strong>
          ${(o.print_count > 0 || o.kitchen_print_count > 0) ? `
             <div style="font-size: 0.75rem; display:inline-block; margin-right:8px;">
               ${o.print_count > 0 ? `<span title="مرات طباعة الكاشير" style="background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:4px;margin-left:4px;border:1px solid #bae6fd"><i class="fas fa-print"></i> كاشير: ${o.print_count}</span>` : ''}
               ${o.kitchen_print_count > 0 ? `<span title="مرات طباعة المطبخ" style="background:#ffedd5;color:#c2410c;padding:2px 6px;border-radius:4px;border:1px solid #fed7aa"><i class="fas fa-print"></i> مطبخ: ${o.kitchen_print_count}</span>` : ''}
             </div>
          ` : ''}
        </div>
        ${o.table_number ? `<span class="table-badge" style="background: var(--secondary); padding: 4px 12px; font-size: .85rem; color: #fff; border-radius: 20px;">طاولة ${o.table_number}</span>` : ''}
        ${statusBadge(o.status)}
      </div>
      <div style="font-size: .82rem; color: var(--text-muted); margin-bottom: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
        <span style="grid-column: 1/-1"><i class="far fa-clock"></i> ${formatDate(o.created_at)}</span>
        <span><i class="fas fa-user"></i> الويتر: <strong>${o.waiter_name}</strong></span>
        <span><i class="fas fa-user-tag"></i> المباشر: <strong>${o.direct_name || 'غير محدد'}</strong></span>
        <span style="grid-column: 1/-1"><i class="fas fa-cash-register"></i> الكاشير: <strong>${o.cashier_name || 'لم يؤكد بعد'}</strong></span>
      </div>
      <div class="order-items-preview" style="flex:1">
        ${(o.items || []).slice(0,3).map(i => `
          <div class="order-item-line" style="display:flex; justify-content:space-between; font-size:.85rem; padding:4px 0; border-bottom:1px dashed var(--border);">
            <span>${i.item_number ? `[${i.item_number}] ` : ''}${i.item_name_ar} x${i.quantity}</span>
            <span>${parseFloat(i.subtotal).toFixed(2)}</span>
          </div>`).join('')}
        ${o.item_count > 3 ? `<span style="color:var(--primary);font-size:.8rem">+ ${o.item_count-3} أصناف أخرى</span>` : ''}
      </div>
      ${o.notes ? `<div style="font-size:.8rem;color:var(--text-muted);margin-top:8px"><i class="fas fa-sticky-note"></i> ${o.notes}</div>` : ''}
      <div class="order-card-footer" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:10px; margin-top:10px;">
        <strong style="color:var(--primary)">${parseFloat(o.total).toFixed(2)} ريال</strong>
        <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
          <button class="btn btn-secondary btn-sm" onclick="viewOrder(${o.id})" title="التفاصيل"><i class="fas fa-eye"></i></button>
          ${o.status !== 'paid' && o.status !== 'cancelled' ? `
            <button class="btn btn-warning btn-sm" onclick="cancelOrder(${o.id})" title="إلغاء الطلب"><i class="fas fa-times-circle"></i> إلغاء</button>` : ''}
          <button class="btn btn-danger btn-sm" onclick="deleteOrder(${o.id})" title="حذف نهائي من الجذور"><i class="fas fa-trash-alt"></i> حذف</button>
        </div>
      </div>
    </div>
  `).join('');

  renderPagination(ordersData.length, limit, currentPage, 'pagination-container', 'setPage');
}

function setPage(p) {
  currentPage = p;
  renderOrders();
}

async function deleteOrder(id) {
  if (!confirmAction('سيتم حذف هذا الطلب نهائياً من الجذور (بما في ذلك سجلات المواد المطلوبة). هل أنت متأكد؟')) return;
  const res = await apiCall('/api/orders.php?action=delete', 'POST', {id: id});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadOrders([id.toString()]);
}

async function viewOrder(id) {
  const res = await apiCall('/api/orders.php?action=single&id=' + id);
  if (!res.success) return;
  const o = res.data;
  document.getElementById('detail-order-number').textContent = '#' + o.order_number;
  document.getElementById('order-detail-body').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
      <div><strong>الطاولة:</strong> ${o.table_number || 'غير محدد'}</div>
      <div><strong>الحالة:</strong> ${statusBadge(o.status)}</div>
      <div><strong>الويتر (مُدخل الطلب):</strong> ${o.waiter_name}</div>
      <div><strong>المباشر (متابع الطلب):</strong> ${o.direct_name || 'غير محدد'}</div>
      <div><strong>الكاشير (مؤكد الطلب):</strong> ${o.cashier_name || 'لم يؤكد بعد'}</div>
      <div><strong>التاريخ:</strong> ${formatDate(o.created_at)}</div>
    </div>
    ${o.notes ? `<div class="alert alert-info"><i class="fas fa-sticky-note"></i> ${o.notes}</div>` : ''}
    <table>
      <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th><th>الحالة</th></tr></thead>
      <tbody>
        ${o.items.map(i => `<tr>
          <td>${i.item_number ? `[${i.item_number}] ` : ''}${i.item_name_ar} ${i.status === 'rejected' ? '<span class="badge badge-danger" style="font-size:.7rem">مرفوض</span>' : ''}</td>
          <td>${i.quantity}</td>
          <td>${parseFloat(i.unit_price).toFixed(2)}</td>
          <td style="${i.status === 'rejected' ? 'text-decoration:line-through;color:var(--danger)' : ''}">${parseFloat(i.subtotal).toFixed(2)}</td>
          <td>${statusBadge(i.status)}</td>
        </tr>`).join('')}
      </tbody>
    </table>
    <div class="receipt-row bold mt-12" style="font-size:1.1rem;justify-content:flex-end;gap:5px;flex-direction:column;align-items:flex-end">
      <div>
        <span>إجمالي الفاتورة الأصلية:</span>
        <span style="${o.refund_amount > 0 ? 'text-decoration:line-through;color:var(--text-muted)' : 'color:var(--primary)'}">${parseFloat(o.total).toFixed(2)} ريال</span>
      </div>
      ${o.refund_amount > 0 ? `
      <div style="color:var(--danger)">
        <span>المرتجع:</span>
        <span>-${parseFloat(o.refund_amount).toFixed(2)} ريال</span>
      </div>
      <div style="font-size:1.3rem;font-weight:900;color:var(--primary);margin-top:5px;border-top:2px dashed var(--border);padding-top:5px">
        <span>الصافي:</span>
        <span>${parseFloat(o.total - o.refund_amount).toFixed(2)} ريال</span>
      </div>` : ''}
    </div>
  `;
  document.getElementById('order-detail-actions').innerHTML = `
    <button class="btn btn-secondary" onclick="closeDetailModal()">إغلاق</button>
    <button class="btn btn-warning" onclick="window.location.href='../waiter/index.php?append_to=${o.id}'"><i class="fas fa-plus"></i> إضافة أصناف</button>
    ${window.POS_USER.can_print ? (
      o.status === 'paid'
        ? `<button class="btn btn-primary" onclick="printReceipt(${o.id})"><i class="fas fa-print"></i> طباعة الفاتورة</button>`
        : `<button class="btn btn-primary" disabled title="لا يمكن طباعة الفاتورة إلا بعد تأكيد الدفع من الكاشير" style="opacity:0.45;cursor:not-allowed"><i class="fas fa-lock"></i> الفاتورة (${statusLabels[o.status]?.label || o.status})</button>`
    ) : ''}
  `;
  document.getElementById('order-detail-modal').classList.remove('hidden');
}

function closeDetailModal() {
  document.getElementById('order-detail-modal').classList.add('hidden');
}

async function cancelOrder(id) {
  if (!confirmAction('إلغاء هذا الطلب؟')) return;
  const res = await apiCall('/api/orders.php?action=update_status', 'POST', {order_id: id, status: 'cancelled'});
  showToast(res.message, res.success ? 'success' : 'danger');
  if (res.success) loadOrders();
}

document.getElementById('filter-status').addEventListener('change', () => { currentPage = 1; loadOrders(); });
document.getElementById('filter-from-date').addEventListener('change', () => { currentPage = 1; loadOrders(); });
document.getElementById('filter-to-date').addEventListener('change', () => { currentPage = 1; loadOrders(); });
document.getElementById('order-detail-modal').addEventListener('click', e => { if(e.target.id==='order-detail-modal') closeDetailModal(); });

// SSE - Real-time updates
(function waitForApp() {
  if (typeof apiCall === 'function') {
    loadOrders();
    if (typeof onSSE === 'function') {
      onSSE('new_order', function(data) { showToast('طلب جديد: ' + (data.order_number||''), 'success'); loadOrders(); });
      onSSE('order_status_changed', loadOrders);
      onSSE('item_status_changed', loadOrders);
      onSSE('order_deleted', loadOrders);
    }
  } else { setTimeout(waitForApp, 50); }
})();

</script>

<?php adminFooter(); ?>
