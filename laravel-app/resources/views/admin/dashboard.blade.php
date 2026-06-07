@extends('layouts.admin')
@section('title', 'لوحة التحكم')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
@endpush

@section('content')

{{-- ─── Filter Bar ─── --}}
<div class="card mb-16 no-print">
    <div class="card-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-left:4px solid var(--primary)">
        <strong style="color:var(--primary)"><i class="fas fa-filter"></i> تصفية النطاق الزمني:</strong>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <input type="date" class="form-control" id="dash-from" style="width:auto" value="{{ now()->subDays(6)->toDateString() }}">
            <span style="color:var(--text-muted)">إلى</span>
            <input type="date" class="form-control" id="dash-to" style="width:auto" value="{{ today()->toDateString() }}">
            <button class="btn btn-primary btn-sm" onclick="loadDashboard()"><i class="fas fa-check"></i> تطبيق</button>
        </div>
        <div style="display:flex;gap:10px;margin-right:auto">
            <button id="btn-pdf" class="btn btn-sm" onclick="exportPDF()" style="background:#e74c3c;color:#fff;border:none;padding:8px 15px;border-radius:10px;cursor:pointer;font-weight:600">
                <i class="fas fa-file-pdf"></i> تصدير PDF
            </button>
            <button id="btn-excel" class="btn btn-sm" onclick="exportExcel()" style="background:#27ae60;color:#fff;border:none;padding:8px 15px;border-radius:10px;cursor:pointer;font-weight:600">
                <i class="fas fa-file-excel"></i> تصدير Excel
            </button>
        </div>
    </div>
</div>

{{-- ─── Stats Cards ─── --}}
<div class="stats-grid" id="stats-container">
    <a href="{{ route('admin.reports.index') }}" class="stat-card" style="text-decoration: none; color: inherit; display: flex;">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div><div class="stat-value" id="today-rev">...</div><div class="stat-label">إيرادات اليوم</div></div>
    </a>
    <a href="{{ route('admin.orders.index') }}" class="stat-card success" style="text-decoration: none; color: inherit; display: flex;">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value" id="pending-orders">...</div><div class="stat-label">طلبات نشطة</div></div>
    </a>
    <a href="{{ route('admin.reports.index') }}" class="stat-card info" style="text-decoration: none; color: inherit; display: flex;">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div><div class="stat-value" id="period-rev">...</div><div class="stat-label">إيرادات الفترة</div></div>
    </a>
    <a href="{{ route('admin.items.index') }}" class="stat-card warning" style="text-decoration: none; color: inherit; display: flex;">
        <div class="stat-icon"><i class="fas fa-utensils"></i></div>
        <div><div class="stat-value" id="items-count">...</div><div class="stat-label">إجمالي الأصناف</div></div>
    </a>
</div>

{{-- ─── Charts Row 1 ─── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:20px">
    <div class="card" style="border-top:4px solid var(--primary)">
        <div class="card-header" style="border-bottom:none;padding-bottom:0">
            <h3><i class="fas fa-chart-area" style="color:var(--primary)"></i> إيرادات الفترة المحددة</h3>
        </div>
        <div class="card-body"><canvas id="revenueChart" style="width:100%;height:250px"></canvas></div>
    </div>
    <div class="card" style="border-top:4px solid var(--info)">
        <div class="card-header" style="border-bottom:none;padding-bottom:0">
            <h3><i class="fas fa-chart-pie" style="color:var(--info)"></i> مبيعات الفئات</h3>
        </div>
        <div class="card-body" style="display:flex;justify-content:center;align-items:center;min-height:250px">
            <canvas id="categoriesChart" style="max-height:250px"></canvas>
        </div>
    </div>
</div>

{{-- ─── Charts Row 2 ─── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:20px">
    <div class="card" style="border-top:4px solid var(--success)">
        <div class="card-header" style="border-bottom:none;padding-bottom:0">
            <h3><i class="fas fa-users" style="color:var(--success)"></i> أفضل الويترز النشطين</h3>
        </div>
        <div class="card-body"><canvas id="waitersChart" style="width:100%;height:250px"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> أحدث الطلبات النشطة</h3>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline btn-sm">عرض الكل</a>
        </div>
        <div class="card-body" style="padding:0;overflow-y:auto;max-height:300px">
            <div id="recent-orders"><div style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div></div>
        </div>
    </div>
</div>

{{-- ─── Low Stock ─── --}}
<div id="low-stock-section"></div>

@endsection

@push('scripts')
<script>
Chart.defaults.font.family = "'Tajawal', sans-serif";
Chart.defaults.color = '#7f8c8d';

let revChart = null, catChart = null, waitChart = null, lastData = null;

const statusMap = {
    pending:         ['warning','معلق'],
    sent_to_cashier: ['info','للكاشير'],
    confirmed:       ['primary','مؤكد'],
    in_progress:     ['warning','جاري'],
    ready:           ['success','جاهز'],
    paid:            ['success','مدفوع'],
    cancelled:       ['danger','ملغي'],
};

function statusBadge(s) {
    const [c,l] = statusMap[s] || ['primary', s];
    return `<span class="badge badge-${c}">${l}</span>`;
}

async function loadDashboard() {
    const from = document.getElementById('dash-from').value;
    const to   = document.getElementById('dash-to').value;
    const base = '{{ url("/admin/api/dashboard") }}';

    // ── Stats
    const res = await fetch(`${base}/stats?from=${from}&to=${to}`, {
        headers: {'X-CSRF-TOKEN': window.CSRF_TOKEN}
    }).then(r => r.json());

    if (res.success) {
        const d = res.data;
        document.getElementById('today-rev').textContent     = parseFloat(d.today_revenue||0).toFixed(2) + ' ريال';
        document.getElementById('period-rev').textContent    = parseFloat(d.period_revenue||0).toFixed(2) + ' ريال';
        document.getElementById('pending-orders').textContent= d.active_orders || 0;
        document.getElementById('items-count').textContent   = d.items_count || 0;

        // Recent orders
        const oDiv = document.getElementById('recent-orders');
        if (d.recent_orders && d.recent_orders.length) {
            oDiv.innerHTML = `<table style="margin:0"><thead><tr>
                <th style="padding:10px 15px">الطلب</th>
                <th style="padding:10px 15px">طاولة</th>
                <th style="padding:10px 15px">الإجمالي</th>
                <th style="padding:10px 15px">الحالة</th>
            </tr></thead><tbody>
                ${d.recent_orders.map(o => `<tr>
                    <td style="padding:10px 15px"><strong>#${o.order_number}</strong><br><small style="color:var(--text-muted)">${o.waiter_name||''}</small></td>
                    <td style="padding:10px 15px">${o.table_number||'-'}</td>
                    <td style="padding:10px 15px;color:var(--primary);font-weight:700">${parseFloat(o.total).toFixed(2)}</td>
                    <td style="padding:10px 15px">${statusBadge(o.status)}</td>
                </tr>`).join('')}
            </tbody></table>`;
        } else {
            oDiv.innerHTML = '<div class="empty-state" style="padding:30px;text-align:center;color:var(--text-muted)"><i class="fas fa-paste fa-2x"></i><p>لا توجد طلبات</p></div>';
        }

        // Low stock
        if (d.low_stock && d.low_stock.length) {
            document.getElementById('low-stock-section').innerHTML = `
            <div class="card" style="border-top:4px solid var(--danger)">
                <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> مخزون منخفض (${d.low_stock.length} صنف)</h3></div>
                <div class="table-responsive"><table>
                    <thead><tr><th>الصنف</th><th>المتوفر</th><th>الحد الأدنى</th><th>الوحدة</th></tr></thead>
                    <tbody>${d.low_stock.map(i => `<tr>
                        <td><strong>${i.name}</strong></td>
                        <td><span class="badge badge-danger">${i.current_stock}</span></td>
                        <td>${i.min_stock}</td>
                        <td>${i.unit}</td>
                    </tr>`).join('')}</tbody>
                </table></div>
            </div>`;
        }
    }

    // ── Charts
    const cRes = await fetch(`${base}/charts?from=${from}&to=${to}`, {
        headers: {'X-CSRF-TOKEN': window.CSRF_TOKEN}
    }).then(r => r.json());

    if (cRes.success) {
        lastData = cRes.data;
        renderCharts(cRes.data);
    }
}

function renderCharts(data) {
    // Revenue Line Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    if (revChart) revChart.destroy();
    let grad = revCtx.createLinearGradient(0,0,0,300);
    grad.addColorStop(0,'rgba(230,126,34,.4)');
    grad.addColorStop(1,'rgba(230,126,34,0)');
    revChart = new Chart(revCtx, {
        type:'line',
        data:{
            labels: data.trend.map(t => new Date(t.date).toLocaleDateString('ar-EG',{weekday:'short',day:'numeric'})),
            datasets:[{label:'الإيرادات',data:data.trend.map(t=>t.revenue),borderColor:'#e67e22',backgroundColor:grad,borderWidth:3,tension:.4,fill:true,pointBackgroundColor:'#fff',pointBorderColor:'#e67e22',pointRadius:4,pointHoverRadius:6}]
        },
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{borderDash:[5,5]},ticks:{callback:v=>v+' ر'}},x:{grid:{display:false}}}}
    });

    // Categories Doughnut
    const catCtx = document.getElementById('categoriesChart').getContext('2d');
    if (catChart) catChart.destroy();
    catChart = new Chart(catCtx, {
        type:'doughnut',
        data:{
            labels:data.categories.map(c=>c.cat_name),
            datasets:[{data:data.categories.map(c=>c.total_revenue),backgroundColor:['#f39c12','#3498db','#e74c3c','#2ecc71','#9b59b6','#34495e','#e67e22'],borderWidth:2,borderColor:'#fff'}]
        },
        options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'right',rtl:true,labels:{usePointStyle:true,padding:15}}}}
    });

    // Waiters Bar Chart
    const waitCtx = document.getElementById('waitersChart').getContext('2d');
    if (waitChart) waitChart.destroy();
    waitChart = new Chart(waitCtx, {
        type:'bar',
        data:{
            labels:data.waiters.map(w=>w.waiter_name),
            datasets:[{label:'عدد الطلبات',data:data.waiters.map(w=>w.orders_count),backgroundColor:'#2ecc71',borderRadius:6,barPercentage:.6}]
        },
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{borderDash:[5,5]}},x:{grid:{display:false}}}}
    });
}

function exportPDF() {
    const btn = document.getElementById('btn-pdf');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التوليد...';
    const from = document.getElementById('dash-from').value;
    const to   = document.getElementById('dash-to').value;
    const opt = {margin:[10,5],filename:`تقرير_${from}_${to}.pdf`,image:{type:'jpeg',quality:.98},html2canvas:{scale:2,useCORS:true},jsPDF:{unit:'mm',format:'a4',orientation:'landscape'}};
    html2pdf().set(opt).from(document.querySelector('.page-content')).save().finally(()=>{
        btn.disabled=false; btn.innerHTML='<i class="fas fa-file-pdf"></i> تصدير PDF';
    });
}

function exportExcel() {
    if (!lastData) { alert('جرب تطبيق الفلتر أولاً'); return; }
    const from = document.getElementById('dash-from').value, to = document.getElementById('dash-to').value;
    const wb = XLSX.utils.book_new();
    const wsCat = XLSX.utils.aoa_to_sheet([['الفئة','إجمالي المبيعات'],...lastData.categories.map(c=>[c.cat_name,+c.total_revenue])]);
    XLSX.utils.book_append_sheet(wb, wsCat, 'مبيعات الفئات');
    const wsW = XLSX.utils.aoa_to_sheet([['الموظف','الطلبات','الإجمالي'],...lastData.waiters.map(w=>[w.waiter_name,+w.orders_count,+w.total_revenue])]);
    XLSX.utils.book_append_sheet(wb, wsW, 'أداء الموظفين');
    const wsT = XLSX.utils.aoa_to_sheet([['التاريخ','الإيراد','الطلبات'],...lastData.trend.map(t=>[t.date,+t.revenue,+t.orders])]);
    XLSX.utils.book_append_sheet(wb, wsT, 'حركة الإيرادات');
    XLSX.writeFile(wb, `تقرير_${from}_${to}.xlsx`);
}

// Auto-load
loadDashboard();
setInterval(loadDashboard, 30000);

// SSE
const sse = new EventSource('{{ url("/api/sse") }}');
sse.addEventListener('new_order', e => {
    const d = JSON.parse(e.data);
    const n = document.createElement('div');
    n.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#27ae60;color:#fff;padding:12px 24px;border-radius:10px;z-index:9999;font-size:.95rem';
    n.innerHTML = `🆕 طلب جديد #${d.order_number} - طاولة ${d.table}`;
    document.body.appendChild(n);
    setTimeout(()=>n.remove(), 4000);
    loadDashboard();
});
sse.addEventListener('order_status_changed', loadDashboard);
</script>
@endpush
