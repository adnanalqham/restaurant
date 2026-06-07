@extends('layouts.admin')

@section('title', 'مراقبة الطلبات (الكاشير)')

@section('content')
<style>
    /* Reset some admin defaults to match legacy feel */
    .page-content { padding: 0 !important; background: #f5f5f0; }
    
    .cashier-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 0;
        height: calc(100vh - 110px);
        direction: rtl;
    }

    /* Orders Area (Right side in RTL flow) */
    .orders-list-side {
        padding: 20px;
        overflow-y: auto;
        border-left: 1px solid #e0ddd8;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    /* Legacy Order Card Style */
    .order-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        border: 1px solid #e0ddd8;
        border-right: 6px solid #e67e22;
        padding: 15px;
        cursor: pointer;
        transition: 0.25s;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .order-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
    .order-card.selected { border: 2px solid #e67e22; border-right-width: 6px; background: #fffcf5; }
    
    .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0; }
    .order-num { font-weight: 900; font-size: 1.15rem; color: #e67e22; }
    .table-badge { background: #2c3e50; color: #fff; padding: 3px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; }
    
    .card-meta { font-size: 0.8rem; color: #7f8c8d; margin-bottom: 10px; display: flex; gap: 10px; }
    
    .items-preview { flex: 1; margin-bottom: 12px; }
    .item-line { display: flex; justify-content: space-between; font-size: 0.85rem; padding: 4px 0; border-bottom: 1px dashed #eee; color: #2c2c2c; }
    
    .card-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f0f0f0; padding-top: 10px; }
    .card-total { font-weight: 900; font-size: 1.1rem; color: #e67e22; }
    .details-btn { background: #f0f2f5; color: #3498db; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }

    /* Detail Side (Left side in RTL flow) */
    .detail-side {
        background: #fff;
        display: flex;
        flex-direction: column;
        box-shadow: -5px 0 15px rgba(0,0,0,0.05);
        z-index: 10;
    }

    .detail-header {
        background: #2c3e50;
        color: #fff;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
    }

    .detail-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }

    .detail-item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .detail-item-name { font-weight: 700; font-size: 0.9rem; color: #333; }
    .detail-item-price { font-weight: 800; color: #e67e22; }

    .summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.9rem; color: #555; }
    .summary-total { display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 2px solid #eee; font-weight: 900; font-size: 1.3rem; color: #e67e22; }

    .detail-actions {
        padding: 20px;
        background: #fdfdfd;
        border-top: 2px solid #eee;
    }

    .btn-action {
        width: 100%; padding: 12px; border-radius: 12px; border: none; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 10px;
    }
    .btn-confirm { background: #27ae60; color: #fff; box-shadow: 0 4px 12px rgba(39,174,96,0.3); }
    .btn-confirm:hover { background: #219150; }
    
    .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .btn-secondary-action { background: #f0f2f5; color: #555; padding: 10px; border-radius: 10px; border: 1px solid #ddd; font-weight: 700; font-size: 0.85rem; cursor: pointer; text-align: center; }

    .status-badge { padding: 4px 10px; border-radius: 30px; font-size: 0.75rem; font-weight: 700; }
    .badge-pending { background: #fef9e7; color: #f39c12; }
    .badge-sent { background: #eaf2ff; color: #3498db; }
    .badge-ready { background: #d5f5e3; color: #27ae60; }
    .badge-paid { background: #d1f2eb; color: #16a085; }

    .empty-state { text-align: center; padding: 100px 40px; color: #ccc; }
    .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.5; }
</style>

<div class="cashier-grid">
    {{-- Right: Orders List --}}
    <div class="orders-list-side">
        <div class="section-header">
            <h2 style="margin:0"><i class="fas fa-inbox"></i> الطلبات الواردة</h2>
            <div style="display:flex; gap:10px">
                <select id="status-filter" class="form-control" style="width:auto" onchange="loadOrders()">
                    <option value="active">الطلبات النشطة</option>
                    <option value="sent_to_cashier">أرسلت للكاشير</option>
                    <option value="confirmed">مؤكدة</option>
                    <option value="ready">جاهزة</option>
                    <option value="all">كل الطلبات</option>
                </select>
                <button class="btn btn-outline" onclick="loadOrders()"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>

        <div class="orders-grid" id="orders-grid">
            {{-- Dynamically filled --}}
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>

    {{-- Left: Selected Order Details --}}
    <aside class="detail-side">
        <div class="detail-header">
            <span><i class="fas fa-file-invoice"></i> تفاصيل الطلب</span>
            <span id="selected-order-num">---</span>
        </div>
        
        <div class="detail-body" id="detail-body">
            <div class="empty-state">
                <i class="fas fa-mouse-pointer"></i>
                <p>اختر طلباً من القائمة لعرض التفاصيل</p>
            </div>
        </div>

        <div class="detail-actions" id="detail-actions" style="display:none">
            <div class="form-group" style="margin-bottom:15px">
                <label style="display:block; font-size:0.8rem; font-weight:700; color:#666; margin-bottom:5px">طريقة الدفع</label>
                <select id="pay-method" class="form-control">
                    <option value="cash">💵 نقداً (كاش)</option>
                    <option value="wallet">👛 محفظة رقمية</option>
                </select>
            </div>
            
            <button class="btn-action btn-confirm" id="confirm-btn" onclick="confirmPayment()">
                <i class="fas fa-check-circle"></i> تأكيد الدفع وإغلاق الطلب
            </button>
            
            <div class="action-grid">
                <button class="btn-secondary-action" onclick="printReceipt()"><i class="fas fa-print"></i> فاتورة</button>
                <button class="btn-secondary-action" onclick="addItems()"><i class="fas fa-plus"></i> إضافة أصناف</button>
                <button class="btn-secondary-action" onclick="openDiscount()"><i class="fas fa-percent"></i> خصم</button>
                <button class="btn-secondary-action" style="color:#e74c3c" onclick="cancelOrder()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div>
    </aside>
</div>

{{-- Discount Modal (legacy style) --}}
<div id="discount-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center">
    <div style="background:#fff; width:90%; max-width:400px; border-radius:15px; padding:20px">
        <h3 style="margin:0 0 15px 0">إضافة خصم</h3>
        <div class="form-group">
            <label>قيمة الخصم</label>
            <input type="number" id="disc-val" class="form-control" placeholder="0.00">
        </div>
        <div class="form-group">
            <label>نوع الخصم</label>
            <select id="disc-type" class="form-control">
                <option value="fixed">مبلغ ثابت (ريال)</option>
                <option value="percent">نسبة مئوية (%)</option>
            </select>
        </div>
        <div style="display:flex; gap:10px; margin-top:20px">
            <button class="btn btn-primary flex-1" onclick="applyDiscount()">تطبيق</button>
            <button class="btn btn-secondary" onclick="closeDiscount()">إلغاء</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let currentOrders = [];
let selectedOrderId = null;

async function loadOrders() {
    const status = document.getElementById('status-filter').value;
    try {
        const res = await fetch(`{{ url('api/orders') }}?status=${status}`).then(r => r.json());
        if (res.success) {
            currentOrders = res.data;
            renderOrders();
        }
    } catch(e) { console.error(e); }
}

function renderOrders() {
    const grid = document.getElementById('orders-grid');
    if (currentOrders.length === 0) {
        grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><i class="fas fa-check-double"></i><p>لا توجد طلبات حالياً</p></div>';
        return;
    }

    grid.innerHTML = currentOrders.map(o => {
        const items = o.items || [];
        const previewHtml = items.slice(0, 3).map(i => `
            <div class="item-line">
                <span>${i.item_name_ar} x${i.quantity}</span>
                <span>${parseFloat(i.subtotal).toFixed(2)}</span>
            </div>
        `).join('');

        return `
            <div class="order-card ${selectedOrderId === o.id ? 'selected' : ''}" onclick="selectOrder(${o.id})">
                <div class="card-top">
                    <span class="order-num">#${o.order_number}</span>
                    <span class="table-badge">طاولة ${o.table_number || 'تيك أوي'}</span>
                </div>
                <div class="card-meta">
                    <span><i class="far fa-clock"></i> ${new Date(o.created_at).toLocaleTimeString('ar-YE', {hour:'2-digit', minute:'2-digit'})}</span>
                    <span><i class="fas fa-user"></i> ${o.waiter_name}</span>
                </div>
                <div class="items-preview">
                    ${previewHtml}
                    ${items.length > 3 ? `<div style="text-align:center; color:#999; font-size:0.7rem; margin-top:5px">+ ${items.length-3} أصناف أخرى</div>` : ''}
                </div>
                <div class="card-footer">
                    <div class="card-total">${parseFloat(o.total).toFixed(2)} ريال</div>
                    <div class="details-btn">للتفاصيل <i class="fas fa-chevron-left"></i></div>
                </div>
            </div>
        `;
    }).join('');
}

async function selectOrder(id) {
    selectedOrderId = id;
    renderOrders();
    
    const order = currentOrders.find(o => o.id === id);
    document.getElementById('selected-order-num').textContent = '#' + order.order_number;
    document.getElementById('detail-actions').style.display = 'block';
    
    const body = document.getElementById('detail-body');
    body.innerHTML = '<div style="text-align:center; padding:50px"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    // Fetch full detail with items
    const res = await fetch(`{{ url('api/orders') }}/${id}`).then(r => r.json());
    if (res.success) {
        const o = res.data;
        body.innerHTML = `
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.85rem; margin-bottom:15px; color:#666">
                <div><strong>الطاولة:</strong> ${o.table_number || '---'}</div>
                <div><strong>الويتر:</strong> ${o.waiter_name}</div>
                <div><strong>الوقت:</strong> ${new Date(o.created_at).toLocaleString('ar-YE')}</div>
                <div><strong>الحالة:</strong> <span class="status-badge badge-${o.status}">${o.status}</span></div>
            </div>
            
            <div style="border:1px solid #eee; border-radius:10px; overflow:hidden">
                ${o.items.map(i => `
                    <div class="detail-item-row" style="padding:10px 15px">
                        <div>
                            <div class="detail-item-name">${i.item_name_ar}</div>
                            <small style="color:#999">x${i.quantity} @ ${parseFloat(i.unit_price).toFixed(2)}</small>
                            ${i.size_name ? `<span style="font-size:0.7rem; background:#f0f0f0; padding:2px 6px; border-radius:4px; margin-right:5px">${i.size_name}</span>` : ''}
                        </div>
                        <div class="detail-item-price">${parseFloat(i.subtotal).toFixed(2)}</div>
                    </div>
                `).join('')}
            </div>

            <div style="margin-top:15px; padding:15px; background:#fcfcfc; border-radius:10px">
                <div class="summary-row"><span>المجموع الفرعي:</span> <span>${parseFloat(o.subtotal).toFixed(2)}</span></div>
                ${o.discount_amount > 0 ? `<div class="summary-row" style="color:#e74c3c"><span>الخصم:</span> <span>-${parseFloat(o.discount_amount).toFixed(2)}</span></div>` : ''}
                <div class="summary-total">
                    <span>الإجمالي النهائي</span>
                    <span>${parseFloat(o.total).toFixed(2)} ريال</span>
                </div>
            </div>
        `;
    }
}

async function confirmPayment() {
    if (!selectedOrderId) return;
    const btn = document.getElementById('confirm-btn');
    const method = document.getElementById('pay-method').value;
    
    if (!confirm('هل تم استلام المبلغ وتأكيد الدفع؟')) return;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

    try {
        const res = await fetch(`{{ url('api/orders/update-status') }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ order_id: selectedOrderId, status: 'paid', payment_method: method })
        }).then(r => r.json());

        if (res.success) {
            alert('تم تأكيد الدفع وإغلاق الطلب');
            selectedOrderId = null;
            document.getElementById('detail-actions').style.display = 'none';
            document.getElementById('selected-order-num').textContent = '---';
            document.getElementById('detail-body').innerHTML = '<div class="empty-state"><i class="fas fa-check-circle" style="color:#27ae60"></i><p>تم إكمال الطلب بنجاح</p></div>';
            loadOrders();
        } else {
            alert(res.message);
        }
    } catch(e) { alert('فشل الاتصال بالسيرفر'); }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> تأكيد الدفع وإغلاق الطلب';
}

function openDiscount() { if (selectedOrderId) document.getElementById('discount-modal').style.display = 'flex'; }
function closeDiscount() { document.getElementById('discount-modal').style.display = 'none'; }

async function applyDiscount() {
    const val = document.getElementById('disc-val').value;
    const type = document.getElementById('disc-type').value;
    if (!val) return;

    const res = await fetch(`{{ url('api/orders/apply-discount') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ order_id: selectedOrderId, discount_type: type, discount_value: val })
    }).then(r => r.json());

    if (res.success) {
        closeDiscount();
        selectOrder(selectedOrderId);
        loadOrders();
    } else {
        alert(res.message);
    }
}

function printReceipt() {
    if (selectedOrderId) window.open(`{{ url('admin/orders') }}/${selectedOrderId}/print`, '_blank');
}

function addItems() {
    if (selectedOrderId) window.location.href = `{{ url('waiter') }}?append_to=${selectedOrderId}`;
}

async function cancelOrder() {
    if (!selectedOrderId || !confirm('هل أنت متأكد من إلغاء الطلب بالكامل؟')) return;
    const res = await fetch(`{{ url('api/orders/update-status') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ order_id: selectedOrderId, status: 'cancelled' })
    }).then(r => r.json());
    
    if (res.success) {
        selectedOrderId = null;
        loadOrders();
        document.getElementById('detail-body').innerHTML = '<div class="empty-state"><p>تم إلغاء الطلب</p></div>';
        document.getElementById('detail-actions').style.display = 'none';
    }
}

// SSE and Auto-refresh
loadOrders();
const sse = new EventSource(`{{ url('api/sse') }}`);
sse.addEventListener('new_order', loadOrders);
sse.addEventListener('order_status_changed', loadOrders);
setInterval(loadOrders, 30000);
</script>
@endpush
