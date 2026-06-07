<?php
require_once __DIR__ . '/_layout.php';
adminHeader('لوحة التحكم', 'dashboard');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div class="card mb-16 no-print">
  <div class="card-body"
    style="padding:14px 20px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; background: var(--bg-card); border-radius:15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 4px solid var(--primary);">
    <strong style="color:var(--secondary); font-size:1.05rem;"><i class="fas fa-filter"></i> تصفية النطاق
      الزمني:</strong>
    <div style="display:flex; align-items:center; gap:8px;">
      <input type="date" class="form-control" id="dash-from" style="width:auto"
        value="<?php echo date('Y-m-d', strtotime('-6 days')); ?>">
      <span style="color:var(--text-muted)">إلى</span>
      <input type="date" class="form-control" id="dash-to" style="width:auto" value="<?php echo date('Y-m-d'); ?>">
      <button class="btn btn-primary btn-sm" onclick="applyDashboardFilter()"><i class="fas fa-check"></i>
        تطبيق</button>
    </div>

    <div style="display:flex; gap:10px; margin-right:auto">
      <button id="btn-pdf" class="btn btn-sm" onclick="exportDashboardPDF()"
        style="background:#e74c3c; color:#fff; border:none; padding:8px 15px; border-radius:10px; cursor:pointer; font-weight:600">
        <i class="fas fa-file-pdf"></i> تصدير PDF
      </button>
      <button id="btn-excel" class="btn btn-sm" onclick="exportDashboardExcel()"
        style="background:#27ae60; color:#fff; border:none; padding:8px 15px; border-radius:10px; cursor:pointer; font-weight:600">
        <i class="fas fa-file-excel"></i> تصدير Excel
      </button>
    </div>
  </div>
</div>

<div id="stats-container">
  <div class="stats-grid">
    <div class="stat-card" id="stat-today">
      <div class="stat-icon"><i class="fas fa-coins"></i></div>
      <span class="stat-value" id="today-rev">...</span>
      <div class="stat-label">إيرادات اليوم</div>
    </div>
    <div class="stat-card success" id="stat-pending">
      <div class="stat-icon"><i class="fas fa-receipt"></i></div>
      <span class="stat-value" id="pending-orders">...</span>
      <div class="stat-label">طلبات نشطة</div>
    </div>
    <div class="stat-card info">
      <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
      <span class="stat-value" id="month-rev">...</span>
      <div class="stat-label">إيرادات الفترة</div>
    </div>
    <div class="stat-card danger" id="stat-wallet">
      <div class="stat-icon"><i class="fas fa-wallet"></i></div>
      <span class="stat-value" id="period-wallet">...</span>
      <div class="stat-label">مبيعات المحافظ (الفترة)</div>
      <small style="font-size: 0.72rem; opacity: 0.8; display: block; margin-top: 4px;" id="today-wallet">اليوم:
        ...</small>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon"><i class="fas fa-utensils"></i></div>
      <span class="stat-value" id="items-count">...</span>
      <div class="stat-label">إجمالي الأصناف</div>
    </div>
  </div>
</div>

<!-- Charts Row 1 -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px;">
  <!-- Line Chart: Revenue Trend -->
  <div class="card"
    style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px; border-top: 4px solid var(--primary);">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
      <h3><i class="fas fa-chart-area" style="color:var(--primary)"></i> إيرادات أخر 7 أيام</h3>
    </div>
    <div class="card-body">
      <canvas id="revenueChart" style="width: 100%; height: 250px;"></canvas>
    </div>
  </div>

  <!-- Doughnut Chart: Top Categories -->
  <div class="card"
    style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px; border-top: 4px solid var(--info);">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
      <h3><i class="fas fa-chart-pie" style="color:var(--info)"></i> مبيعات الفئات (الفترة المحددة)</h3>
    </div>
    <div class="card-body" style="display: flex; justify-content: center; align-items: center; min-height: 250px;">
      <canvas id="categoriesChart" style="max-height: 250px;"></canvas>
    </div>
  </div>
</div>

<!-- Row 2 -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px;">
  <!-- Bar Chart: Top Waiters -->
  <div class="card"
    style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px; border-top: 4px solid var(--success);">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
      <h3><i class="fas fa-users" style="color:var(--success)"></i> أفضل الويترز النشطين (الفترة المحددة)</h3>
    </div>
    <div class="card-body">
      <canvas id="waitersChart" style="width: 100%; height: 250px;"></canvas>
    </div>
  </div>

  <!-- Bar Chart: Top Cashiers -->
  <div class="card"
    style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px; border-top: 4px solid var(--warning);">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
      <h3><i class="fas fa-cash-register" style="color:var(--warning)"></i> مبيعات الكاشير (الفترة المحددة)</h3>
    </div>
    <div class="card-body">
      <canvas id="cashiersChart" style="width: 100%; height: 250px;"></canvas>
    </div>
  </div>

  <!-- Bar Chart: Wallets Breakdown -->
  <div class="card"
    style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px; border-top: 4px solid var(--danger);">
    <div class="card-header" style="border-bottom:none; padding-bottom:0;">
      <h3><i class="fas fa-wallet" style="color:var(--danger)"></i> مبيعات المحافظ الرقمية (الفترة المحددة)</h3>
    </div>
    <div class="card-body">
      <canvas id="walletsChart" style="width: 100%; height: 250px;"></canvas>
    </div>
  </div>
</div>

<!-- Recent Orders Table Row -->
<div class="card mb-20" style="box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius:15px;">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h3><i class="fas fa-history"></i> أحدث الطلبات النشطة</h3>
    <a href="orders.php" class="btn btn-outline btn-primary btn-sm">عرض الكل</a>
  </div>
  <div class="card-body" style="padding:0; overflow-y: auto; max-height: 300px;">
    <div id="recent-orders">
      <div class="spinner border-primary"></div>
    </div>
  </div>
</div>

<script>
  // Chart Instances & Data Persistence
  let revChartInstance = null;
  let catChartInstance = null;
  let waitChartInstance = null;
  let cashChartInstance = null;
  let walletChartInstance = null;
  let lastChartData = null;

  // Global Chart configurations
  Chart.defaults.font.family = "'Tajawal', sans-serif";
  Chart.defaults.color = '#7f8c8d';

  async function loadDashboard() {
    const from = document.getElementById('dash-from').value;
    const to = document.getElementById('dash-to').value;
    let params = '';
    if (from && to) params = `&from=${from}&to=${to}`;

    // Load summary stats
    const res = await apiCall('/api/reports.php?action=summary' + params);
    if (res.success) {
      const d = res.data;
      document.getElementById('today-rev').textContent = parseFloat(d.today_revenue || 0).toFixed(2) + ' ريال';
      document.getElementById('month-rev').textContent = parseFloat(d.period_revenue || 0).toFixed(2) + ' ريال';
      document.getElementById('pending-orders').textContent = d.pending_orders || 0;
      document.getElementById('period-wallet').textContent = parseFloat(d.period_wallet || 0).toFixed(2) + ' ريال';
      document.getElementById('today-wallet').textContent = 'اليوم: ' + parseFloat(d.today_wallet || 0).toFixed(2) + ' ريال';
    }

    // Load items count
    const iRes = await apiCall('/api/items.php?all=1');
    if (iRes.success) document.getElementById('items-count').textContent = iRes.data.length;

    // Recent orders
    const oRes = await apiCall('/api/orders.php?limit=6');
    const oDiv = document.getElementById('recent-orders');
    if (oRes.success && oRes.data.length > 0) {
      oDiv.innerHTML = `<table style="margin:0">
      <thead><tr><th style="padding:10px 15px; color:#fff;">الطلب</th><th style="padding:10px 15px; color:#fff;">طاولة</th><th style="padding:10px 15px; color:#fff;">الإجمالي</th><th style="padding:10px 15px; color:#fff;">الحالة</th></tr></thead>
      <tbody>
        ${oRes.data.map(o => `<tr>
          <td style="padding:10px 15px">
            <strong>#${o.order_number}</strong>
            ${(o.print_count > 0 || o.kitchen_print_count > 0) ? `
               <div style="font-size: 0.7rem; display:block; margin-top:4px;">
                 ${o.print_count > 0 ? `<span title="طباعة كاشير" style="background:#e0f2fe;color:#0369a1;padding:1px 4px;border-radius:3px;margin-left:2px;border:1px solid #bae6fd"><i class="fas fa-print"></i> ك:${o.print_count}</span>` : ''}
                 ${o.kitchen_print_count > 0 ? `<span title="طباعة مطبخ" style="background:#ffedd5;color:#c2410c;padding:1px 4px;border-radius:3px;border:1px solid #fed7aa"><i class="fas fa-print"></i> م:${o.kitchen_print_count}</span>` : ''}
               </div>
            ` : ''}
          </td>
          <td style="padding:10px 15px">${o.table_number || '-'}</td>
          <td style="color:var(--primary);font-weight:700;padding:10px 15px">${parseFloat(o.total).toFixed(2)}</td>
          <td style="padding:10px 15px">${statusBadge(o.status)}</td>
        </tr>`).join('')}
      </tbody></table>`;
    } else {
      oDiv.innerHTML = '<div class="empty-state"><span class="icon"><i class="fas fa-paste"></i></span><p>لا توجد طلبات</p></div>';
    }

    // --- Advanced Charts ---
    const cRes = await apiCall('/api/reports.php?action=dashboard_charts' + params);
    if (cRes.success) {
      lastChartData = cRes.data; // Store for export
      renderCharts(cRes.data);
    }
  }

  function renderCharts(data) {
    // 1. Revenue Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    if (revChartInstance) revChartInstance.destroy();

    // Gradient for Revenue line
    let gradientFill = revCtx.createLinearGradient(0, 0, 0, 300);
    gradientFill.addColorStop(0, "rgba(230, 126, 34, 0.4)");
    gradientFill.addColorStop(1, "rgba(230, 126, 34, 0.0)");

    revChartInstance = new Chart(revCtx, {
      type: 'line',
      data: {
        labels: data.trend.map(t => new Date(t.date).toLocaleDateString('ar-EG', { weekday: 'short', day: 'numeric' })),
        datasets: [{
          label: 'الإيرادات اليومية',
          data: data.trend.map(t => t.revenue),
          borderColor: '#e67e22',
          backgroundColor: gradientFill,
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#e67e22',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { callback: function (value) { return value + ' ر.ي'; } } },
          x: { grid: { display: false } }
        }
      }
    });

    // 2. Categories Doughnut Chart
    const catCtx = document.getElementById('categoriesChart').getContext('2d');
    if (catChartInstance) catChartInstance.destroy();

    let catColors = ['#f39c12', '#3498db', '#e74c3c', '#2ecc71', '#9b59b6', '#34495e'];

    catChartInstance = new Chart(catCtx, {
      type: 'doughnut',
      data: {
        labels: data.categories.map(c => c.cat_name),
        datasets: [{
          data: data.categories.map(c => c.total_revenue),
          backgroundColor: catColors,
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { position: 'right', rtl: true, labels: { usePointStyle: true, padding: 15 } }
        }
      }
    });

    // 3. Waiters Bar Chart
    const waitCtx = document.getElementById('waitersChart').getContext('2d');
    if (waitChartInstance) waitChartInstance.destroy();

    waitChartInstance = new Chart(waitCtx, {
      type: 'bar',
      data: {
        labels: data.waiters.map(w => w.waiter_name),
        datasets: [
          {
            label: 'عدد الطلبات المفذة',
            data: data.waiters.map(w => w.orders_count),
            backgroundColor: '#2ecc71',
            borderRadius: 6,
            barPercentage: 0.6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
          x: { grid: { display: false } }
        }
      }
    });

    // 4. Cashiers Bar Chart
    const cashCtx = document.getElementById('cashiersChart').getContext('2d');
    if (cashChartInstance) cashChartInstance.destroy();

    cashChartInstance = new Chart(cashCtx, {
      type: 'bar',
      data: {
        labels: data.cashiers.map(c => c.cashier_name),
        datasets: [
          {
            label: 'إجمالي المبيعات (ريال)',
            data: data.cashiers.map(c => c.total_revenue),
            backgroundColor: '#f39c12',
            borderRadius: 6,
            barPercentage: 0.6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (context) { return context.parsed.y + ' ريال'; } } } },
        scales: {
          y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
          x: { grid: { display: false } }
        }
      }
    });

    // 5. Wallets Bar Chart
    const walletCtx = document.getElementById('walletsChart').getContext('2d');
    if (walletChartInstance) walletChartInstance.destroy();

    walletChartInstance = new Chart(walletCtx, {
      type: 'bar',
      data: {
        labels: data.wallets.map(w => w.wallet_name),
        datasets: [
          {
            label: 'إجمالي الإيداعات (ريال)',
            data: data.wallets.map(w => w.total_revenue),
            backgroundColor: '#e74c3c',
            borderRadius: 6,
            barPercentage: 0.6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (context) { return context.parsed.y + ' ريال'; } } } },
        scales: {
          y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  function applyDashboardFilter() {
    loadDashboard();
  }

  async function exportDashboardPDF() {
    const btn = document.getElementById('btn-pdf');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التوليد...';

    const element = document.querySelector('.main-content');
    const from = document.getElementById('dash-from').value;
    const to = document.getElementById('dash-to').value;

    const opt = {
      margin: [10, 5, 10, 5],
      filename: `تقرير_الأداء_${from}_إلى_${to}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2, useCORS: true, letterRendering: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };

    // Clean UI for print
    const hideItems = document.querySelectorAll('.no-print, .sidebar-toggle, .topbar, .btn-outline-primary, .sidebar');
    const mainContent = document.querySelector('.main-content');
    if (mainContent) mainContent.style.margin = '0';
    hideItems.forEach(el => { if (el) el.style.setProperty('display', 'none', 'important'); });

    try {
      await html2pdf().set(opt).from(element).save();
    } catch (e) {
      console.error(e);
    } finally {
      hideItems.forEach(el => { if (el) el.style.display = ''; });
      if (mainContent) mainContent.style.margin = '';
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  function exportDashboardExcel() {
    if (!lastChartData) {
      showToast('يرجى تطبيق التصفية أولاً لجلب البيانات', 'warning');
      return;
    }

    const btn = document.getElementById('btn-excel');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>...';

    const from = document.getElementById('dash-from').value;
    const to = document.getElementById('dash-to').value;

    const wb = XLSX.utils.book_new();

    // 1. Summary Sheet
    const summaryData = [
      ["تقرير أداء المنشأة", ""],
      ["الفترة الزمنية", `من ${from} إلى ${to}`],
      ["", ""],
      ["المؤشر", "القيمة"],
      ["إيرادات الفترة", document.getElementById('month-rev').textContent],
      ["إيرادات اليوم الحالي", document.getElementById('today-rev').textContent],
      ["إجمالي الطلبات النشطة", document.getElementById('pending-orders').textContent],
      ["عدد الأصناف في المنيو", document.getElementById('items-count').textContent],
      ["مبيعات المحافظ (الفترة)", document.getElementById('period-wallet').textContent],
      ["مبيعات المحافظ (اليوم)", document.getElementById('today-wallet').textContent.replace('اليوم: ', '')]
    ];
    const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, wsSummary, "الملخص العام");

    // 2. Categories Sheet
    const catData = [["الفئة", "إجمالي المبيعات (ريال)"]];
    lastChartData.categories.forEach(c => catData.push([c.cat_name, parseFloat(c.total_revenue)]));
    const wsCat = XLSX.utils.aoa_to_sheet(catData);
    XLSX.utils.book_append_sheet(wb, wsCat, "مبيعات الفئات");

    // 3. Waiters Sheet
    const waiterData = [["اسم المباشر", "عدد الطلبات", "إجمالي القيمة"]];
    lastChartData.waiters.forEach(w => waiterData.push([w.waiter_name, parseInt(w.orders_count), parseFloat(w.total_revenue)]));
    const wsWaiter = XLSX.utils.aoa_to_sheet(waiterData);
    XLSX.utils.book_append_sheet(wb, wsWaiter, "أداء الموظفين");

    // 3.5 Cashiers Sheet
    const cashierData = [["اسم الكاشير", "عدد الطلبات", "إجمالي المبيعات"]];
    lastChartData.cashiers.forEach(c => cashierData.push([c.cashier_name, parseInt(c.orders_count), parseFloat(c.total_revenue)]));
    const wsCashier = XLSX.utils.aoa_to_sheet(cashierData);
    XLSX.utils.book_append_sheet(wb, wsCashier, "مبيعات الكاشير");

    // 3.8 Wallets Sheet
    const walletExcelData = [["اسم المحفظة", "عدد عمليات الإيداع", "إجمالي المبيعات"]];
    lastChartData.wallets.forEach(w => walletExcelData.push([w.wallet_name, parseInt(w.orders_count), parseFloat(w.total_revenue)]));
    const wsWallet = XLSX.utils.aoa_to_sheet(walletExcelData);
    XLSX.utils.book_append_sheet(wb, wsWallet, "مبيعات المحافظ الرقمية");

    // 4. Trend Sheet
    const trendData = [["التاريخ", "الإيراد اليومي", "عدد الطلبات"]];
    lastChartData.trend.forEach(t => trendData.push([t.date, parseFloat(t.revenue), parseInt(t.orders)]));
    const wsTrend = XLSX.utils.aoa_to_sheet(trendData);
    XLSX.utils.book_append_sheet(wb, wsTrend, "حركة الإيرادات");

    XLSX.writeFile(wb, `تقرير_مفصل_${from}_إلى_${to}.xlsx`);

    btn.disabled = false;
    btn.innerHTML = originalHtml;
    showToast('تم تصدير ملف Excel بنجاح', 'success');
  }

  (function waitForApp() {
    if (typeof apiCall === 'function') {
      loadDashboard();
      setInterval(loadDashboard, 30000);
      if (typeof onSSE === 'function') {
        onSSE('new_order', function (data) {
          showToast('🆕 طلب جديد: ' + (data.order_number || '') + ' (طاولة ' + (data.table || '?') + ')', 'success', 5000);
          loadDashboard();
        });
        onSSE('order_status_changed', loadDashboard);
      }
    } else { setTimeout(waitForApp, 50); }
  })();
</script>

<?php adminFooter(); ?>