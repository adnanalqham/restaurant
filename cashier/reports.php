<?php
require_once __DIR__ . '/_layout.php';
cashierHeader('التقارير المالية', 'reports');
?>

<!-- Filters -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 16px">
    <div class="flex gap-12" style="flex-wrap:wrap; justify-content: space-between; align-items: center">
      <div class="flex gap-12">
        <input type="date" id="report-date" class="form-control" style="width:auto" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary" onclick="loadReport()"><i class="fas fa-search"></i> عرض التقرير</button>
      </div>
        <select class="form-control" onchange="if(this.value) { downloadReport(this.value); this.value=''; }" style="width:auto; font-weight:600; cursor:pointer; background:#2ecc71; color:#fff; border-color:#2ecc71;">
          <option value="" disabled selected style="background:#fff; color:#333;">📊 تصدير تقرير كـــ</option>
          <option value="summary" style="background:#fff; color:#333;">تصدير تقرير كـــ (عادي)</option>
          <option value="detailed" style="background:#fff; color:#333;">تصدير تقرير كـــ (تفصيلي)</option>
        </select>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-16">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
    <span class="stat-value" id="s-total">0</span>
    <div class="stat-label">إجمالي الطلبات</div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    <span class="stat-value" id="s-paid">0</span>
    <div class="stat-label">مدفوعة</div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon"><i class="fas fa-coins"></i></div>
    <span class="stat-value" id="s-rev">0</span>
    <div class="stat-label">الإيرادات</div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon"><i class="fas fa-undo"></i></div>
    <span class="stat-value" id="s-refund">0</span>
    <div class="stat-label">مرتجع الشيف</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
    <span class="stat-value" id="s-items">0</span>
    <div class="stat-label">إجمالي الحبات المباعة</div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
    <span class="stat-value" id="s-avg">0</span>
    <div class="stat-label">متوسط الطلب</div>
  </div>
</div>

<div class="card mb-16">
  <div class="card-header">
    <h3><i class="fas fa-fire"></i> الأصناف الأكثر مبيعاً</h3>
  </div>
  <div class="card-body">
    <div id="items-grid" class="items-stat-grid">
      <div style="text-align:center;padding:20px;color:var(--text-muted);grid-column:1/-1">اختر تاريخاً لعرض الإحصائيات
      </div>
    </div>
  </div>
</div>

<style>
  .items-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
    padding: 5px;
  }

  .item-stat-card {
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .item-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow);
    border-color: var(--primary);
  }

  .item-stat-card .qty-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--secondary);
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: .75rem;
    font-weight: 700;
  }

  .item-stat-card .item-icon {
    font-size: 1.5rem;
    color: var(--primary);
    margin-bottom: 10px;
    display: block;
  }

  .item-stat-card .item-name {
    font-weight: 700;
    font-size: .9rem;
    color: var(--text-main);
    margin-bottom: 8px;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .item-stat-card .item-rev {
    font-size: .85rem;
    color: var(--success);
    font-weight: 600;
  }
</style>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-history"></i> سجل الطلبات اليومية</h3>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>رقم الطلب</th>
            <th>عدد الأصناف</th>
            <th>الطاولة</th>
            <th>الويتر</th>
            <th>الوقت</th>
            <th>الإجمالي</th>
            <th>الحالة</th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody id="report-tbody">
          <tr>
            <td colspan="8" style="text-align:center;padding:30px">
              <div class="spinner"></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div id="pagination-container"></div>
  </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
  let reportData = [];
  let currentPage = 1;

  document.getElementById('report-date').addEventListener('change', () => { currentPage = 1; loadReport(); });

  async function loadReport() {
    const date = document.getElementById('report-date').value;
    const res = await apiCall('/api/reports.php?action=daily&date=' + date);
    if (!res.success) return;
    reportData = res.data.orders || [];
    const stats = res.data.stats || {};
    document.getElementById('s-total').textContent = stats.total_orders || 0;
    document.getElementById('s-paid').textContent = stats.paid_orders || 0;
    document.getElementById('s-rev').textContent = parseFloat(stats.total_revenue || 0).toFixed(2) + ' ريال';
    document.getElementById('s-refund').textContent = parseFloat(stats.refunded_amount || 0).toFixed(2) + ' ريال';
    document.getElementById('s-items').textContent = stats.total_pieces || 0;
    document.getElementById('s-avg').textContent = parseFloat(stats.avg_order_value || 0).toFixed(2) + ' ريال';

    // Render Items Stats
    const itemsGrid = document.getElementById('items-grid');
    const topItems = res.data.top_items || [];
    if (!topItems.length) {
      itemsGrid.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);grid-column:1/-1">لا توجد مبيعات أصناف لهذا اليوم</div>';
    } else {
      itemsGrid.innerHTML = topItems.map(i => `
      <div class="item-stat-card">
        <span class="qty-badge">${i.total_qty}</span>
        <div class="item-icon">
          ${i.cat_icon && i.cat_icon.includes('fa-') ? `<i class="${i.cat_icon}"></i>` : (i.cat_icon || '🍔')}
        </div>
        <span class="item-name">${i.item_name_ar}</span>
        <div class="item-rev">${parseFloat(i.total_revenue).toFixed(2)} ريال</div>
      </div>
    `).join('');
    }

    const tbody = document.getElementById('report-tbody');
    if (!reportData.length) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state" style="padding: 40px"><span class="icon"><i class="fas fa-folder-open"></i></span><p>لا توجد طلبات لهذا التاريخ</p></div></td></tr>';
      document.getElementById('pagination-container').innerHTML = '';
      return;
    }

    const limit = 10;
    const paginatedReports = paginateData(reportData, currentPage, limit);

    tbody.innerHTML = paginatedReports.map(o => `<tr>
    <td><strong>${o.order_number}</strong></td>
    <td><span class="badge badge-info">${o.item_count} أصناف</span></td>
    <td>${o.table_number || '-'}</td>
    <td>${o.waiter_name}</td>
    <td style="font-size:.8rem"><i class="far fa-clock"></i> ${formatDate(o.created_at)}</td>
    <td style="color:var(--primary);font-weight:700">${parseFloat(o.total).toFixed(2)}</td>
    <td>${statusBadge(o.status)}</td>
    <td><button class="btn btn-info btn-sm" onclick="printReceipt(${o.id})"><i class="fas fa-print"></i></button></td>
  </tr>`).join('');

    renderPagination(reportData.length, limit, currentPage, 'pagination-container', 'setPage');
  }

  function setPage(p) {
    currentPage = p;

    const tbody = document.getElementById('report-tbody');
    const limit = 10;
    const paginatedReports = paginateData(reportData, currentPage, limit);

    tbody.innerHTML = paginatedReports.map(o => `<tr>
    <td><strong>${o.order_number}</strong></td>
    <td><span class="badge badge-info">${o.item_count} أصناف</span></td>
    <td>${o.table_number || '-'}</td>
    <td>${o.waiter_name}</td>
    <td style="font-size:.8rem"><i class="far fa-clock"></i> ${formatDate(o.created_at)}</td>
    <td style="color:var(--primary);font-weight:700">${parseFloat(o.total).toFixed(2)}</td>
    <td>${statusBadge(o.status)}</td>
    <td><button class="btn btn-info btn-sm" onclick="printReceipt(${o.id})"><i class="fas fa-print"></i></button></td>
  </tr>`).join('');

    renderPagination(reportData.length, limit, currentPage, 'pagination-container', 'setPage');
  }

  function downloadReport(mode) {
    if (!reportData.length) { showToast('لا توجد بيانات لتصديرها', 'warning'); return; }

    // Filter to check if this cashier has processed any orders first
    let filteredData = reportData;
    if (window.POS_USER && window.POS_USER.role === 'cashier') {
      filteredData = reportData.filter(o => parseInt(o.cashier_id) === parseInt(window.POS_USER.id));
    }
    if (!filteredData.length) { showToast('لا توجد طلبات قمت بالعمل عليها لتصديرها', 'warning'); return; }

    const date = document.getElementById('report-date').value;
    const url = `<?= BASE_PATH ?>api/export_report.php?mode=${mode}&date=${date}`;
    window.open(url, '_blank');
  }

  document.addEventListener('DOMContentLoaded', loadReport);
</script>
<?php cashierFooter(); ?>