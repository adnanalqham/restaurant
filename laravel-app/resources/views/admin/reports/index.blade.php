@extends('layouts.admin')
@section('title', 'التقارير المالية')

@section('content')
<style>
    :root {
        --color-avg: #e67e22;
        --color-pieces: #f39c12;
        --color-discounts: #d35400;
        --color-refunds: #c0392b;
        --color-revenue: #2980b9;
        --color-paid: #27ae60;
        --color-total: #d35400;
    }

    .report-header-card {
        background: #fff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    .legacy-stat-card {
        border-radius: 12px;
        padding: 20px 10px;
        color: #fff;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
        overflow: hidden;
        transition: transform 0.3s;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .legacy-stat-card:hover { transform: translateY(-5px); }

    .legacy-stat-card .icon {
        font-size: 2rem;
        margin-bottom: 10px;
        opacity: 0.8;
    }

    .legacy-stat-card .value {
        font-size: 1.4rem;
        font-weight: 800;
        margin-bottom: 5px;
    }

    .legacy-stat-card .label {
        font-size: 0.85rem;
        font-weight: 600;
        opacity: 0.9;
    }

    .stat-avg { background: var(--color-avg); }
    .stat-pieces { background: var(--color-pieces); }
    .stat-discounts { background: var(--color-discounts); }
    .stat-refunds { background: var(--color-refunds); }
    .stat-revenue { background: var(--color-revenue); }
    .stat-paid { background: var(--color-paid); }
    .stat-total { background: var(--color-total); }

    .main-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 25px;
    }

    .item-card {
        background: #fff;
        border-radius: 15px;
        padding: 15px;
        text-align: center;
        position: relative;
        border: 1px solid #eee;
        transition: 0.3s;
    }

    .item-card:hover { border-color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

    .item-card .qty-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #2c3e50;
        color: #fff;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.8rem;
        border: 2px solid #fff;
    }

    .item-card i {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 10px;
        display: block;
    }

    .item-card .name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #333;
        margin-bottom: 5px;
        display: block;
    }

    .item-card .price {
        color: var(--color-paid);
        font-weight: 800;
        font-size: 1.1rem;
    }

    .table-legacy thead th {
        background: #2c3e50;
        color: #fff;
        font-weight: 600;
        padding: 15px;
        border: none;
    }

    .table-legacy tbody td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid #f0f0f0;
    }

    .btn-export {
        padding: 8px 15px;
        border-radius: 8px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
</style>

<!-- Filters Header -->
<div class="report-header-card">
    <form method="GET" action="{{ route('admin.reports.index') }}" id="report-form" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px">
        <div style="display:flex; gap:10px; align-items:center">
            <button type="button" class="btn btn-warning" onclick="document.getElementById('report-form').submit()">
                <i class="fas fa-chart-bar"></i> عرض التقرير
            </button>
            <div style="display:flex; gap:5px">
                <input type="date" name="date" class="form-control" value="{{ $date }}" style="width:160px; display:{{ $type === 'daily' ? 'block' : 'none' }}" id="report-date">
                <div id="range-inputs" style="display:{{ $type === 'range' ? 'flex' : 'none' }}; gap:5px; align-items:center">
                    <input type="date" name="from" class="form-control" value="{{ $from }}" style="width:140px">
                    <span>إلى</span>
                    <input type="date" name="to" class="form-control" value="{{ $to }}" style="width:140px">
                </div>
                <select name="type" class="form-control" style="width:120px" onchange="toggleInputs(this.value)">
                    <option value="daily" {{ $type === 'daily' ? 'selected' : '' }}>يومي</option>
                    <option value="range" {{ $type === 'range' ? 'selected' : '' }}>نطاق تاريخ</option>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:10px">
            <button type="button" class="btn btn-success btn-export" onclick="downloadExcel('detailed')">
                <i class="fas fa-file-excel"></i> تصدير Excel (تفصيلي)
            </button>
            <button type="button" class="btn btn-success btn-export" onclick="downloadExcel('normal')">
                <i class="fas fa-file-excel"></i> تصدير Excel (عادي)
            </button>
        </div>
    </form>
</div>

<!-- Stats Grid -->
<div class="stats-container">
    <div class="legacy-stat-card stat-avg">
        <div class="icon"><i class="fas fa-chart-line"></i></div>
        <div class="value">{{ number_format($stats['avg_order_value'], 2) }} ريال</div>
        <div class="label">متوسط الطلب</div>
    </div>
    <div class="legacy-stat-card stat-pieces">
        <div class="icon"><i class="fas fa-cubes"></i></div>
        <div class="value">{{ number_format($stats['total_pieces']) }}</div>
        <div class="label">إجمالي الحبات المباعة</div>
    </div>
    <div class="legacy-stat-card stat-discounts">
        <div class="icon"><i class="fas fa-percent"></i></div>
        <div class="value">{{ number_format($stats['total_discounts'], 2) }} ريال</div>
        <div class="label">إجمالي الخصومات</div>
    </div>
    <div class="legacy-stat-card stat-refunds">
        <div class="icon"><i class="fas fa-history"></i></div>
        <div class="value">{{ number_format($stats['refunded_amount'], 2) }} ريال</div>
        <div class="label">مرتجع الشيف</div>
    </div>
    <div class="legacy-stat-card stat-revenue">
        <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="value">{{ number_format($stats['total_revenue'], 2) }} ريال</div>
        <div class="label">الإيرادات</div>
    </div>
    <div class="legacy-stat-card stat-paid">
        <div class="icon"><i class="fas fa-check-double"></i></div>
        <div class="value">{{ $stats['paid_orders'] }}</div>
        <div class="label">طلبات مدفوعة</div>
    </div>
    <div class="legacy-stat-card stat-total">
        <div class="icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="value">{{ $stats['total_orders'] }}</div>
        <div class="label">إجمالي الطلبات</div>
    </div>
</div>

<div class="main-grid">
    <!-- Items Stats -->
    <div>
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
                <h3 style="margin:0"><i class="fas fa-utensils"></i> إحصائيات الأصناف المباعة</h3>
                <button type="button" class="btn btn-success btn-sm" onclick="downloadExcel('items')">
                    <i class="fas fa-download"></i> تصدير الأصناف
                </button>
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:20px; padding-top:10px">
                    @foreach($topItems as $item)
                    <div class="item-card">
                        <div class="qty-badge">{{ (int)$item->total_qty }}</div>
                        <i class="{{ $item->cat_icon ?: 'fas fa-utensils' }}"></i>
                        <span class="name">{{ $item->item_name_ar }}</span>
                        <div class="price">{{ number_format($item->total_revenue, 2) }} ريال</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Order Log -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
            <h3 style="margin:0"><i class="fas fa-receipt"></i> سجل الطلبات</h3>
            <div style="font-size:0.85rem; color:var(--text-muted)">
                عرض 
                <select class="form-control" id="rows-limit" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.85rem">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                أسطر
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-legacy" id="orders-table">
                <thead>
                    <tr>
                        <th>الرقم / التاريخ</th>
                        <th>الويتر / الكاشير</th>
                        <th>الأصناف</th>
                        <th>قبل الخصم</th>
                        <th>الخصم</th>
                        <th>الصافي</th>
                        <th>طريقة الدفع</th>
                        <th>الوقت</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="orders-tbody">
                    {{-- Rendered by JS --}}
                </tbody>
            </table>
        </div>
        <div id="pagination-controls" style="padding:15px; display:flex; justify-content:center; gap:5px"></div>
    </div>
</div>

{{-- Order Details Modal --}}
<div class="modal-backdrop hidden" id="order-modal">
    <div class="modal" style="max-width:650px">
        <div class="modal-header">
            <h3>تفاصيل الطلب <span id="modal-order-id"></span></h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" id="modal-content">
            <div style="text-align:center; padding:30px">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function toggleInputs(val) {
    document.getElementById('report-date').style.display = val === 'daily' ? 'block' : 'none';
    document.getElementById('range-inputs').style.display = val === 'range' ? 'flex' : 'none';
}

async function viewOrderDetails(id) {
    document.getElementById('modal-order-id').textContent = '#' + id;
    document.getElementById('order-modal').classList.remove('hidden');
    document.getElementById('modal-content').innerHTML = '<div style="text-align:center; padding:30px"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    
    try {
        const res = await fetch(`{{ url('/admin/orders') }}/${id}/details`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(r => r.text());
        document.getElementById('modal-content').innerHTML = res;
    } catch (e) {
        document.getElementById('modal-content').innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء تحميل البيانات</div>';
    }
}

function closeModal() {
    document.getElementById('order-modal').classList.add('hidden');
}

function downloadExcel(mode) {
    const type = document.querySelector('select[name="type"]').value;
    let url = "";
    if (mode === 'normal') url = "{{ route('admin.reports.export.normal') }}";
    else if (mode === 'detailed') url = "{{ route('admin.reports.export.detailed') }}";
    else if (mode === 'items') url = "{{ route('admin.reports.export.items') }}";

    let from, to;
    if (type === 'daily') {
        from = to = document.querySelector('input[name="date"]').value;
    } else {
        from = document.querySelector('input[name="from"]').value;
        to = document.querySelector('input[name="to"]').value;
    }
    window.location.href = `${url}?from=${from}&to=${to}`;
}

// Client-side pagination logic
let allOrders = @json($orders);
let currentPage = 1;
let currentLimit = 10;

function renderOrders() {
    const tbody = document.getElementById('orders-tbody');
    const start = (currentPage - 1) * currentLimit;
    const end = start + currentLimit;
    const paginated = allOrders.slice(start, end);

    tbody.innerHTML = paginated.map(o => {
        const beforeDiscount = parseFloat(o.total) + parseFloat(o.manual_discount || 0);
        return `
            <tr>
                <td>
                    <div style="font-weight:700">#${o.order_number}</div>
                    <small style="color:#888">${new Date(o.created_at).toLocaleDateString('en-CA')}</small>
                </td>
                <td>
                    <div style="font-size:0.85rem">
                        <i class="fas fa-user-tag" style="width:20px; color:#3498db"></i> 
                        ${o.waiter_name || '---'}
                    </div>
                    <div style="font-size:0.85rem; color:#7f8c8d">
                        <i class="fas fa-cash-register" style="width:20px; color:#27ae60"></i> 
                        ${o.cashier_name || '---'}
                    </div>
                </td>
                <td>
                    <span class="badge badge-info" style="cursor:pointer" onclick="viewOrderDetails(${o.id})">
                        ${o.item_count || 0} أصناف
                    </span>
                </td>
                <td style="font-weight:600">${beforeDiscount.toFixed(2)}</td>
                <td style="color:var(--danger)">${parseFloat(o.manual_discount || 0).toFixed(2)}</td>
                <td style="font-weight:800; color:var(--primary)">${(parseFloat(o.total) - parseFloat(o.refund_amount || 0)).toFixed(2)}</td>
                <td>
                    <span class="badge" style="background:#e8f4fd; color:#2980b9">${o.payment_method === 'cash' ? 'كاش' : 'شبكة'}</span>
                </td>
                <td>
                    <small>${new Date(o.created_at).toLocaleTimeString('en-US', {hour12: false, hour: '2-digit', minute: '2-digit'})}</small>
                </td>
                <td>
                    <span class="badge badge-${o.status === 'paid' ? 'success' : 'secondary'}" style="padding:5px 10px">${o.status === 'paid' ? 'مدفوع' : o.status}</span>
                </td>
                <td>
                    <div style="display:flex; gap:5px">
                        <button class="btn btn-outline btn-sm" title="عرض" onclick="viewOrderDetails(${o.id})"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline btn-sm" title="طباعة الفاتورة" onclick="printOrder('receipt', ${o.id})"><i class="fas fa-print"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    renderPagination();
}

function renderPagination() {
    const totalPages = Math.ceil(allOrders.length / currentLimit);
    const container = document.getElementById('pagination-controls');
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';
    
    // Previous Arrow
    html += `<button class="btn btn-sm btn-outline" onclick="setPage(${Math.max(1, currentPage - 1)})" ${currentPage === 1 ? 'disabled' : ''} style="margin:0 2px"><i class="fas fa-chevron-right"></i></button>`;

    const maxVisible = 5;
    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, start + maxVisible - 1);
    
    if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1);
    }

    if (start > 1) {
        html += `<button class="btn btn-sm btn-outline" onclick="setPage(1)">1</button>`;
        if (start > 2) html += `<span style="padding:0 5px">...</span>`;
    }

    for (let i = start; i <= end; i++) {
        html += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline'}" onclick="setPage(${i})" style="margin:0 2px">${i}</button>`;
    }

    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span style="padding:0 5px">...</span>`;
        html += `<button class="btn btn-sm btn-outline" onclick="setPage(${totalPages})">${totalPages}</button>`;
    }

    // Next Arrow
    html += `<button class="btn btn-sm btn-outline" onclick="setPage(${Math.min(totalPages, currentPage + 1)})" ${currentPage === totalPages ? 'disabled' : ''} style="margin:0 2px"><i class="fas fa-chevron-left"></i></button>`;

    container.innerHTML = html;
}

function setPage(p) {
    currentPage = p;
    renderOrders();
}

async function printOrder(action, id) {
    const btn = event.target.closest('button');
    if (!btn) return;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const res = await fetch(`{{ url('admin/print') }}/${action}/${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        
        const data = await res.json();
        if(typeof showToast === 'function') {
            showToast(data.message, data.success ? 'success' : 'danger');
        } else {
            alert(data.message);
        }
    } catch(e) {
        alert('حدث خطأ في الاتصال بخدمة الطباعة');
    }
    
    btn.innerHTML = oldHtml;
    btn.disabled = false;
}

document.addEventListener('DOMContentLoaded', () => {
    renderOrders();
    document.getElementById('rows-limit').addEventListener('change', (e) => {
        currentLimit = parseInt(e.target.value);
        currentPage = 1;
        renderOrders();
    });
});
</script>
@endpush
