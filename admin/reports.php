<?php
require_once __DIR__ . '/_layout.php';
adminHeader('التقارير المالية', 'reports');
?>

<!-- Date Filters -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <select class="form-control" id="report-type" style="width:auto">
        <option value="daily">يومي</option>
        <option value="range">نطاق تاريخ</option>
      </select>
      <input type="date" class="form-control" id="report-date" style="width:auto" value="<?= date('Y-m-d') ?>">
      <div id="range-inputs" style="display:none;gap:8px;align-items:center" class="flex">
        <input type="date" class="form-control" id="range-from" value="<?= date('Y-m-01') ?>" style="width:auto">
        <span>إلى</span>
        <input type="date" class="form-control" id="range-to" value="<?= date('Y-m-d') ?>" style="width:auto">
      </div>
      <button class="btn btn-primary" onclick="loadReport()"><i class="fas fa-chart-line"></i> عرض التقرير</button>
      <select class="form-control" onchange="if(this.value) { downloadReport(this.value); this.value=''; }"
        style="width:auto; font-weight:600; cursor:pointer; background:#2ecc71; color:#fff; border-color:#2ecc71;">
        <option value="" disabled selected style="background:#fff; color:#333;">📊 تصدير تقرير كـــ</option>
        <option value="summary" style="background:#fff; color:#333;">تصدير تقرير كـــ (عادي)</option>
        <option value="detailed" style="background:#fff; color:#333;">تصدير تقرير كـــ (تفصيلي)</option>
        <option value="management" style="background:#fff; color:#333;">تصدير تقرير كـــ (إداري)</option>
      </select>
    </div>
  </div>
</div>


<!-- Stats -->
<div class="stats-grid mb-16" id="report-stats">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
    <span class="stat-value" id="s-orders">0</span>
    <div class="stat-label">إجمالي الطلبات</div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
    <span class="stat-value" id="s-paid">0</span>
    <div class="stat-label">طلبات مدفوعة</div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon"><i class="fas fa-coins"></i></div>
    <span class="stat-value" id="s-revenue">0 ريال</span>
    <div class="stat-label">الإيرادات</div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
    <span class="stat-value" id="s-wallet">0.00 ريال</span>
    <div class="stat-label">إيداعات المحافظ</div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon"><i class="fas fa-undo"></i></div>
    <span class="stat-value" id="s-refund">0</span>
    <div class="stat-label">مرتجع الشيف</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><i class="fas fa-percent"></i></div>
    <span class="stat-value" id="s-discounts">0 ريال</span>
    <div class="stat-label">إجمالي الخصومات</div>
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

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">
  <!-- Orders Table -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-history"></i> سجل الطلبات</h3>
      <span id="table-count" class="badge badge-info"></span>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-controls"
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:10px 15px 0;">
        <div style="font-size:0.9rem">
          عرض
          <select class="form-control"
            style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.9rem"
            onchange="currentLimit=parseInt(this.value); setPage(1);">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          أسطر
        </div>
      </div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th onclick="toggleSort()" style="cursor:pointer; white-space:nowrap" title="تغيير الترتيب">الرقم /
                التاريخ <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th>
              <th>الأصناف</th>
              <th>قبل الخصم</th>
              <th>الخصم</th>
              <th>الصافي</th>
              <th>الطاولة</th>
              <th>طريقة الدفع</th>
              <th>الوقت</th>
              <th>الحالة</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody id="report-tbody">
            <tr>
              <td colspan="10" style="text-align:center;padding:30px">
                <div class="spinner"></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div id="pagination-container"></div>
    </div>
  </div>

  <!-- Top Items -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
      <h3><i class="fas fa-fire"></i> إحصائيات الأصناف المباعة</h3>
      <button class="btn btn-sm" style="background:#1d6f42; color:#fff" onclick="downloadReport('items')">
        <i class="fas fa-file-excel"></i> تصدير الأصناف
      </button>
    </div>
    <div class="card-body">
      <div id="items-grid" class="items-stat-grid">
        <div style="text-align:center;padding:20px;color:var(--text-muted);grid-column:1/-1">اختر تاريخاً لعرض
          الإحصائيات</div>
      </div>
    </div>
  </div>
</div>

<style>
  .items-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    padding: 5px;
  }

  .item-stat-card {
    background: var(--bg-body);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .item-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow);
    border-color: var(--primary);
  }

  .item-stat-card .qty-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: var(--secondary);
    color: #fff;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: .7rem;
    font-weight: 700;
  }

  .item-stat-card .item-icon {
    font-size: 1.3rem;
    color: var(--primary);
    margin-bottom: 6px;
    display: block;
  }

  .item-stat-card .item-name {
    font-weight: 700;
    font-size: .82rem;
    color: var(--text-main);
    margin-bottom: 5px;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .item-stat-card .item-rev {
    font-size: .8rem;
    color: var(--success);
    font-weight: 600;
  }
</style>

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

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
  // Helper: format money with commas (e.g. 10,000.00)
  function formatMoney(val) {
    let num = parseFloat(val) || 0;
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
  }

  // Helper: format count with commas (e.g. 1,234)
  function formatQty(val) {
    let num = parseInt(val) || 0;
    return new Intl.NumberFormat('en-US').format(num);
  }

  let reportData = [];
  let currentPage = 1;
  let currentLimit = 10;
  let sortAsc = false;

  // Direct PHP-based download (guaranteed to include items)
  function downloadReport(mode) {
    const type = document.getElementById('report-type').value;
    let url = `<?= BASE_PATH ?>api/export_report.php?mode=${mode}`;

    if (type === 'daily') {
      const date = document.getElementById('report-date').value;
      url += `&date=${date}`;
    } else {
      const from = document.getElementById('range-from').value;
      const to = document.getElementById('range-to').value;
      url += `&from=${from}&to=${to}`;
    }

    window.open(url, '_blank');
  }

  function toggleSort() {
    sortAsc = !sortAsc;
    document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
    setPage(1);
  }

  document.getElementById('report-type').addEventListener('change', function () {
    document.getElementById('report-date').style.display = this.value === 'daily' ? 'block' : 'none';
    document.getElementById('range-inputs').style.display = this.value === 'range' ? 'flex' : 'none';
  });

  async function loadReport() {
    const type = document.getElementById('report-type').value;
    const tbody = document.getElementById('report-tbody');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>';

    let res;
    if (type === 'daily') {
      const date = document.getElementById('report-date').value;
      res = await apiCall('/api/reports.php?action=daily&date=' + date);
    } else {
      const from = document.getElementById('range-from').value;
      const to = document.getElementById('range-to').value;
      res = await apiCall('/api/reports.php?action=range&from=' + from + '&to=' + to);
    }

    if (!res.success) return;

    if (type === 'daily') {
      reportData = res.data.orders || [];
    } else {
      reportData = res.data.rows || [];
    }
    currentPage = 1;
    setPage(1);

    if (type === 'daily') {
      const stats = res.data.stats || {};
      document.getElementById('s-orders').textContent = formatQty(stats.total_orders);
      document.getElementById('s-paid').textContent = formatQty(stats.paid_orders);
      document.getElementById('s-revenue').textContent = formatMoney(stats.total_revenue) + ' ريال';
      document.getElementById('s-wallet').textContent = formatMoney(stats.total_wallet) + ' ريال';
      document.getElementById('s-refund').textContent = formatMoney(stats.refunded_amount) + ' ريال';
      document.getElementById('s-discounts').textContent = formatMoney(stats.total_discounts) + ' ريال';
      document.getElementById('s-items').textContent = formatQty(stats.total_pieces);
      document.getElementById('s-avg').textContent = formatMoney(stats.avg_order_value) + ' ريال';
      document.getElementById('table-count').textContent = formatQty(reportData.length) + ' طلب';


    } else {
      const total = res.data.total_revenue || 0;
      const totalWallet = res.data.total_wallet || 0;
      document.getElementById('s-revenue').textContent = formatMoney(total) + ' ريال';
      document.getElementById('s-wallet').textContent = formatMoney(totalWallet) + ' ريال';
      document.getElementById('s-orders').textContent = formatQty(reportData.reduce((s, r) => s + parseInt(r.orders_count), 0));
      document.getElementById('s-paid').textContent = formatQty(reportData.reduce((s, r) => s + parseInt(r.paid_count), 0));
      document.getElementById('s-avg').textContent = '-';
      document.getElementById('table-count').textContent = formatQty(reportData.length) + ' يوم';
    }

    // Load top items
    const itemsGrid = document.getElementById('items-grid');
    const topItems = res.data.top_items || [];
    if (topItems.length) {
      itemsGrid.innerHTML = topItems.map(i => `
        <div class="item-stat-card">
          <span class="qty-badge">${formatQty(i.total_qty)}</span>
          <div class="item-icon">
          ${i.cat_icon && i.cat_icon.includes('fa-') ? `<i class="${i.cat_icon}"></i>` : (i.cat_icon || '🍔')}
        </div>
          <span class="item-name" title="${i.item_name_ar}">${i.item_number ? '(' + i.item_number + ') ' : ''}${i.item_name_ar}</span>
          <div class="item-rev">${formatMoney(i.total_revenue)} ريال</div>
          ${i.cashier_breakdown ? `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:6px; border-top:1px dashed var(--border); padding-top:6px; white-space: normal; line-height:1.25;" title="${i.cashier_breakdown}">${i.cashier_breakdown}</div>` : ''}
        </div>
      `).join('');
    } else {
      itemsGrid.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);grid-column:1/-1">لا توجد مبيعات أصناف</div>';
    }
  }

  function setPage(p) {
    currentPage = p;
    let items = [...reportData];

    // Sort items depending on the type
    const type = document.getElementById('report-type').value;
    if (type === 'daily') {
      items.sort((a, b) => sortAsc ? a.id - b.id : b.id - a.id);
    } else {
      // Strings natively since they are dates 'YYYY-MM-DD'
      items.sort((a, b) => sortAsc ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date));
    }

    const paginatedData = paginateData(items, currentPage, currentLimit);
    const tbody = document.getElementById('report-tbody');

    if (type === 'daily') {
      tbody.innerHTML = paginatedData.length ? paginatedData.map(o => {
        const isCancelled = o.status === 'cancelled';
        const rowStyle = isCancelled ? 'background:rgba(231,76,60,0.06);' : '';
        const beforeDisc = parseFloat(o.total) + parseFloat(o.manual_discount || 0);
        const net = parseFloat(o.total) - parseFloat(o.refund_amount || 0);
        return `<tr style="${rowStyle}">
        <td><strong>${o.order_number}</strong></td>
        <td><span class="badge badge-info">${formatQty(o.item_count)} أصناف</span></td>
        <td>${formatMoney(beforeDisc)}</td>
        <td style="color:var(--danger)">${formatMoney(o.manual_discount || 0)}</td>
        <td style="color:var(--primary);font-weight:700">
          ${formatMoney(net)}
          ${o.refund_amount > 0 ? `<br><small style="color:var(--danger);font-size:.7rem">مرتجع: -${formatMoney(o.refund_amount)}</small>` : ''}
        </td>
        <td>${o.table_number || '-'}</td>
        <td>${o.payment_method === 'cash' ? '<span class="badge badge-success" style="background:#27ae60">كاش</span>' : (o.payment_method === 'wallet' ? `<span class="badge badge-info" style="background:#2980b9">محفظة${o.wallet_name ? '<br><small style="font-size:.7rem">' + o.wallet_name + '</small>' : ''}</span>` : '-')}</td>
        <td style="font-size:.82rem">${formatDate(o.created_at)}</td>
        <td>${statusBadge(o.status)}</td>
        <td style="display:flex;gap:5px;justify-content:center">
          <button class="btn btn-secondary btn-sm" onclick="viewOrder(${o.id})" title="عرض التفاصيل"><i class="fas fa-eye"></i></button>
          ${!isCancelled ? `<button class="btn btn-info btn-sm" onclick="printReceipt(${o.id})" title="طباعة"><i class="fas fa-print"></i></button>` : ''}
        </td>
      </tr>`;
      }).join('') : '';
    } else {
      tbody.innerHTML = paginatedData.length ? paginatedData.map(r => `<tr>
      <td style="font-weight:700">${r.date}</td>
      <td colspan="4">${formatQty(r.orders_count)} طلب (${formatQty(r.paid_count)} مدفوع)</td>
      <td colspan="4" style="color:var(--primary);font-weight:700">${formatMoney(r.revenue)} ريال</td>
      <td></td>
    </tr>`).join('') : '<tr><td colspan="10"><div class="empty-state"><span class="icon"><i class="fas fa-folder-open"></i></span><p>لا توجد بيانات</p></div></td></tr>';
    }
    renderPagination(items.length, currentLimit, currentPage, 'pagination-container', 'setPage');
  }

  async function exportReport(mode = 'summary') {
    if (!reportData.length) { showToast('لا توجد بيانات للتصدير', 'warning'); return; }

    const type = document.getElementById('report-type').value;
    const wb = XLSX.utils.book_new();
    const dateVal = document.getElementById('report-date').value;
    const fname = 'تقرير_' + dateVal + (mode === 'detailed' ? '_تفصيلي' : '') + '.xlsx';

    // ─── Get orders data (already loaded with items from loadReport()) ───
    let orders = reportData;

    // ─── Sheet 1: Summary ──────────────────────────────────────────────
    let summaryData;
    if (type === 'daily') {
      summaryData = orders.map(o => ({
        'رقم الطلب': o.order_number,
        'طريقة الدفع': o.payment_method === 'cash' ? 'كاش' : (o.payment_method === 'wallet' ? ('محفظة' + (o.wallet_name ? ': ' + o.wallet_name : '')) : '-'),
        'الحالة': o.status,
        'الوقت': o.created_at,
        'الويتر': o.waiter_name,
        'قبل الخصم': parseFloat(parseFloat(o.total) + parseFloat(o.manual_discount || 0)).toFixed(2),
        'الخصم': parseFloat(o.manual_discount || 0).toFixed(2),
        'الصافي': parseFloat(o.total - (o.refund_amount || 0)).toFixed(2),
      }));
    } else {
      summaryData = reportData.map(r => ({
        'التاريخ': r.date,
        'عدد الطلبات': r.orders_count,
        'طلبات مدفوعة': r.paid_count,
        'الإيرادات': parseFloat(r.revenue).toFixed(2),
      }));
    }
    const ws1 = XLSX.utils.json_to_sheet(summaryData);
    ws1['!cols'] = Object.keys(summaryData[0]).map(() => ({ wch: 18 }));
    XLSX.utils.book_append_sheet(wb, ws1, 'تقرير عادي');

    // ─── Sheet 2: Detailed (daily only) ───────────────────────────────
    if (type === 'daily' && mode === 'detailed') {
      // Helper: extract items safely regardless of PHP JSON encoding form
      const getItems = (o) => {
        if (!o.items) return [];
        if (Array.isArray(o.items)) return o.items;
        // PHP FETCH_BOTH may encode as {"0":{...},"1":{...}} - convert to array
        return Object.values(o.items);
      };

      // Find the maximum number of items in a single order  
      const maxItems = Math.max(...orders.map(o => getItems(o).length), 1);

      const detailData = orders.map(o => {
        const row = {
          'رقم الطلب': o.order_number,
          'طريقة الدفع': o.payment_method === 'cash' ? 'كاش' : (o.payment_method === 'wallet' ? ('محفظة' + (o.wallet_name ? ': ' + o.wallet_name : '')) : '-'),
          'نوع العميل': o.customer_type === 'room' ? 'غرف' : (o.customer_type === 'staff' ? 'موظفين' : 'عادي'),
          'المرجع': o.customer_ref || '-',
          'الحالة': o.status,
          'الوقت': o.created_at,
          'الويتر': o.waiter_name,
        };

        // Spread items across columns
        const items = getItems(o);
        for (let i = 0; i < maxItems; i++) {
          const itm = items[i];
          row[`الصنف ${i + 1}`] = itm ? `${itm.item_name_ar} (×${itm.quantity})` : '';
        }

        row['قبل الخصم'] = parseFloat(parseFloat(o.total) + parseFloat(o.manual_discount || 0)).toFixed(2);
        row['الخصم'] = parseFloat(o.manual_discount || 0).toFixed(2);
        row['الصافي'] = parseFloat(o.total - (o.refund_amount || 0)).toFixed(2);

        return row;
      });

      const ws2 = XLSX.utils.json_to_sheet(detailData);
      ws2['!cols'] = [
        { wch: 14 },                              // رقم الطلب
        { wch: 14 },                              // طريقة الدفع
        { wch: 12 },                              // نوع العميل
        { wch: 12 },                              // المرجع
        { wch: 12 },                              // الحالة
        { wch: 20 },                              // الوقت
        { wch: 15 },                              // الويتر
        ...Array(maxItems).fill({ wch: 26 }),     // أعمدة الأصناف
        { wch: 14 },                              // قبل الخصم
        { wch: 12 },                              // الخصم
        { wch: 12 },                              // الصافي
      ];
      XLSX.utils.book_append_sheet(wb, ws2, 'تقرير تفصيلي');
    }

    XLSX.writeFile(wb, fname);
    showToast('تم تصدير الملف بنجاح ✅', 'success');
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
        <div><strong>الويتر:</strong> ${o.waiter_name}</div>
        <div><strong>التاريخ:</strong> ${formatDate(o.created_at)}</div>
        ${o.payment_method ? `<div><strong>طريقة الدفع:</strong> ${o.payment_method === 'cash' ? '<span class="badge badge-success">كاش</span>' : `<span class="badge badge-info">محفظة رقمية${o.wallet_name ? ': ' + o.wallet_name : ''}</span>`}</div>` : `<div><strong>طريقة الدفع:</strong> -</div>`}
        ${o.customer_type && o.customer_type !== 'normal' ? `<div><strong>نوع المبيعات:</strong> <span class="badge badge-warning">${o.customer_type === 'room' ? 'غرف' : 'موظفين'} (${o.customer_ref})</span></div>` : ''}
      </div>
      ${o.notes ? `<div class="alert alert-info"><i class="fas fa-sticky-note"></i> ${o.notes}</div>` : ''}
      <table>
        <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th><th>الحالة</th></tr></thead>
        <tbody>
          ${o.items.map(i => `<tr>
            <td>${i.item_number ? '(' + i.item_number + ') ' : ''}${i.item_name_ar} ${i.status === 'rejected' ? '<span class="badge badge-danger" style="font-size:.7rem">مرفوض</span>' : ''}</td>
            <td>${formatQty(i.quantity)}</td>
            <td>${formatMoney(i.unit_price)}</td>
            <td style="${i.status === 'rejected' ? 'text-decoration:line-through;color:var(--danger)' : ''}">${formatMoney(i.subtotal)}</td>
            <td>${statusBadge(i.status)}</td>
          </tr>`).join('')}
        </tbody>
      </table>
      <div class="receipt-row bold mt-12" style="font-size:1.1rem;justify-content:flex-end;gap:5px;flex-direction:column;align-items:flex-end">
        <div>
          <span>إجمالي الفاتورة الأصلية:</span>
          <span style="${o.refund_amount > 0 ? 'text-decoration:line-through;color:var(--text-muted)' : 'color:var(--primary)'}">${formatMoney(o.total)} ريال</span>
        </div>
        ${o.refund_amount > 0 ? `
        <div style="color:var(--danger)">
          <span>المرتجع:</span>
          <span>-${formatMoney(o.refund_amount)} ريال</span>
        </div>
        <div style="font-size:1.3rem;font-weight:900;color:var(--primary);margin-top:5px;border-top:2px dashed var(--border);padding-top:5px">
          <span>الصافي:</span>
          <span>${formatMoney(o.total - o.refund_amount)} ريال</span>
        </div>` : ''}
      </div>
    `;
    document.getElementById('order-detail-actions').innerHTML = `
      <button class="btn btn-secondary" onclick="closeDetailModal()">\u0625\u063a\u0644\u0627\u0642</button>
      ${o.status === 'paid'
        ? `<button class="btn btn-info" onclick="printReceipt(${o.id})"><i class="fas fa-print"></i> \u0637\u0628\u0627\u0639\u0629 \u0627\u0644\u0641\u0627\u062a\u0648\u0631\u0629</button>`
        : `<button class="btn btn-info" disabled title="\u0644\u0627 \u064a\u0645\u0643\u0646 \u0637\u0628\u0627\u0639\u0629 \u0627\u0644\u0641\u0627\u062a\u0648\u0631\u0629 \u0625\u0644\u0627 \u0628\u0639\u062f \u062a\u0623\u0643\u064a\u062f \u0627\u0644\u062f\u0641\u0639 \u0645\u0646 \u0627\u0644\u0643\u0627\u0634\u064a\u0631" style="opacity:0.45;cursor:not-allowed"><i class="fas fa-lock"></i> \u0627\u0644\u0641\u0627\u062a\u0648\u0631\u0629 (${statusLabels[o.status]?.label || o.status})</button>`
      }
    `;
    document.getElementById('order-detail-modal').classList.remove('hidden');
  }

  function closeDetailModal() {
    document.getElementById('order-detail-modal').classList.add('hidden');
  }

  document.getElementById('order-detail-modal').addEventListener('click', e => { if (e.target.id === 'order-detail-modal') closeDetailModal(); });

  document.addEventListener('DOMContentLoaded', loadReport);
</script>

<?php adminFooter(); ?>