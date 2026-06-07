@extends('layouts.admin')

@section('title', 'طلب جديد')

@section('content')
<style>
    /* Global POS Layout */
    .main-content { height: 100vh; overflow: hidden; background: #fff; }
    .page-content { padding: 0 !important; height: calc(100vh - 70px); overflow: hidden; }
    
    .pos-wrapper {
        display: grid;
        grid-template-columns: 1fr 360px;
        grid-template-areas: "menu sidebar";
        height: 100%;
        direction: rtl;
        background: #fdfdfd;
    }

    /* sidebar (Cart) */
    .pos-sidebar {
        grid-area: sidebar;
        background: #fff;
        border-left: 1px solid #eee;
        display: flex;
        flex-direction: column;
        z-index: 20;
    }

    .sidebar-top-card {
        padding: 15px;
        background: #fff;
        border-bottom: 2px solid #f8f9fa;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .total-label { font-weight: 900; color: #e67e22; font-size: 1.1rem; }
    .total-amount { font-weight: 950; color: #e67e22; font-size: 1.6rem; }

    .input-group-custom { margin-bottom: 10px; }
    .input-label { display: block; font-size: 0.8rem; font-weight: 900; color: #333; margin-bottom: 4px; }
    .input-control { width: 100%; padding: 10px; border: 1.5px solid #edf2f7; border-radius: 8px; font-size: 0.9rem; background: #f8fafc; }

    .selector-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
    .selector-btn {
        padding: 8px; border: 2px solid #edf2f7; border-radius: 10px; background: #fff;
        display: flex; flex-direction: column; align-items: center; gap: 4px;
        cursor: pointer; transition: 0.2s; font-weight: 800; font-size: 0.8rem; color: #718096;
    }
    .selector-btn i { font-size: 1.1rem; }
    .selector-btn.active { border-color: #e67e22; background: #fff7ed; color: #e67e22; }

    .btn-send-order {
        flex: 1; padding: 12px; background: #e67e22; color: #fff;
        border: none; border-radius: 10px; font-weight: 950; font-size: 1.1rem;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 4px 10px rgba(230,126,34,0.2);
    }
    .btn-clear-cart {
        width: 45px; height: 45px; border: 1.5px solid #feb2b2; color: #e53e3e;
        border-radius: 10px; background: #fff; display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 1.1rem;
    }

    .cart-list-header {
        background: #1a1a2e; color: #fff; padding: 10px 15px; font-weight: 900; font-size: 0.9rem;
        display: flex; align-items: center; gap: 8px;
    }

    .cart-items-scroll { flex: 1; overflow-y: auto; padding: 10px; }
    .cart-item {
        display: flex; align-items: center; gap: 10px; padding: 10px; border-bottom: 1px solid #f5f5f5;
    }
    .cart-item-info { flex: 1; display: flex; flex-direction: column; }
    .cart-item-name { font-weight: 800; font-size: 0.85rem; color: #1a202c; }
    .cart-item-sub { font-size: 0.8rem; color: #e67e22; font-weight: 900; }
    
    .qty-controls {
        display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 3px 8px; border-radius: 20px;
    }
    .qty-btn {
        width: 22px; height: 22px; border: none; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: #e67e22; color: #fff; cursor: pointer; font-size: 0.9rem;
    }

    /* Menu Side */
    .pos-menu {
        grid-area: menu;
        display: flex;
        flex-direction: column;
        background: #f8fafc;
        overflow: hidden;
    }

    .menu-header-top {
        padding: 15px 20px; background: #fff; border-bottom: 1px solid #e2e8f0;
        display: flex; align-items: center; gap: 20px;
    }
    
    .cat-tabs-row {
        flex: 1; display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none;
    }
    .cat-tabs-row::-webkit-scrollbar { display: none; }
    
    .cat-pill {
        padding: 8px 18px; border-radius: 50px; background: #fff; border: 1.5px solid #eee;
        font-weight: 800; font-size: 0.82rem; cursor: pointer; white-space: nowrap;
        display: flex; align-items: center; gap: 6px; color: #666; transition: 0.2s;
    }
    .cat-pill.active { background: #e67e22; border-color: #e67e22; color: #fff; }

    .search-box-wrapper { position: relative; width: 240px; }
    .search-box-wrapper input { width: 100%; padding: 8px 38px 8px 12px; border-radius: 10px; border: 1.5px solid #eee; font-size: 0.9rem; font-weight: 600; }
    .search-box-wrapper i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; }

    .menu-scroll-area { flex: 1; overflow-y: auto; padding: 20px; }
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 15px;
    }

    .item-card {
        background: #fff; border-radius: 15px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        cursor: pointer; transition: 0.3s; border: 2px solid transparent;
        display: flex; flex-direction: column; position: relative;
    }
    .item-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
    .item-card.selected { border-color: #e67e22; background: #fffcf9; }

    .card-media { position: relative; padding-top: 75%; overflow: hidden; background: #fdfdfd; }
    .card-media img { position: absolute; top:0; left:0; width:100%; height:100%; object-fit: cover; }

    .card-body { padding: 12px; text-align: right; flex: 1; display: flex; flex-direction: column; position: relative; }
    
    .card-info-btn {
        position: absolute; top: 12px; left: 12px; color: #cbd5e0; font-size: 0.9rem; cursor: pointer; transition: 0.2s; z-index: 10;
    }
    .card-info-btn:hover { color: #e67e22; transform: scale(1.2); }
    
    .card-title { font-weight: 800; font-size: 0.95rem; color: #1a202c; display: block; margin-bottom: 2px; padding-left: 20px; }
    .card-subtitle { font-weight: 500; font-size: 0.75rem; color: #a0aec0; display: block; margin-bottom: 8px; padding-left: 20px; }
    .card-price { font-weight: 950; color: #e67e22; font-size: 1.1rem; margin-top: auto; }

    /* Modal Backdrop for Description */
    .desc-modal {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;
        backdrop-filter: blur(5px);
    }
    .desc-card {
        background: #fff; width: 90%; max-width: 450px; border-radius: 20px; padding: 25px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .item-qty-badge {
        position: absolute; top: 10px; right: 10px; background: #e67e22; color: #fff;
        width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; font-weight: 900; box-shadow: 0 3px 6px rgba(0,0,0,0.2); border: 2px solid #fff; z-index: 10;
    }
</style>

<div class="pos-wrapper">
    {{-- sidebar (Cart) --}}
    <aside class="pos-sidebar">
        <div class="sidebar-top-card">
            <div class="total-row">
                <span class="total-label">الإجمالي:</span>
                <span class="total-amount" id="cart-total">0.00 ريال</span>
            </div>

            <div class="selector-grid">
                <div class="input-group-custom" style="margin:0">
                    <label class="input-label">رقم الطاولة *</label>
                    <input type="number" id="table-number" class="input-control" placeholder="مثال: 5">
                </div>
                <div class="input-group-custom" style="margin:0">
                    <label class="input-label">طريقة الدفع</label>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px">
                        <div class="selector-btn active" id="pay-cash" onclick="setPayMethod('cash')">
                            <i class="fas fa-money-bill-wave"></i> <span>نقد</span>
                        </div>
                        <div class="selector-btn" id="pay-wallet" onclick="setPayMethod('wallet')">
                            <i class="fas fa-wallet"></i> <span>محفظة</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-group-custom">
                <textarea id="order-notes" class="input-control" rows="1" placeholder="ملاحظات الطلب (إختياري)..." style="resize:none"></textarea>
            </div>

            <div class="input-group-custom">
                <label class="input-label">الكاشير المسؤول *</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px">
                    @foreach($cashiers as $idx => $c)
                    <div class="selector-btn {{ $idx == 0 ? 'active' : '' }} cashier-opt" data-id="{{ $c->id }}" onclick="setCashier({{ $c->id }}, this)">
                        <i class="fas fa-user-tie"></i> <span>{{ $c->name }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:15px">
                <button class="btn-send-order" onclick="submitOrder()">
                    <i class="fas fa-paper-plane"></i> إرسال الطلب
                </button>
                <button class="btn-clear-cart" onclick="clearCart()">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>

        <div class="cart-list-header">
            <i class="fas fa-shopping-basket"></i> السلة الحالية
        </div>

        <div class="cart-items-scroll" id="cart-list">
            <div style="text-align:center; padding:50px 20px; color:#cbd5e0">
                <i class="fas fa-cart-plus fa-3x" style="opacity:0.2; margin-bottom:10px"></i>
                <p style="font-weight:700">السلة فارغة</p>
            </div>
        </div>
    </aside>

    {{-- Menu Side --}}
    <main class="pos-menu">
        <div class="menu-header-top">
            <div class="cat-tabs-row">
                <div class="cat-pill active" data-id="all" onclick="setCat('all', this)"><i class="fas fa-th-large"></i> الكل</div>
                <div class="cat-pill" data-id="offers" onclick="setCat('offers', this)" style="border-color: #ffd43b; color: #fab005;"><i class="fas fa-star"></i> العروض</div>
                @foreach($categories as $cat)
                <div class="cat-pill" data-id="{{ $cat->id }}" onclick="setCat({{ $cat->id }}, this)">
                    <i class="fas fa-utensils"></i> {{ $cat->name_ar }}
                </div>
                @endforeach
            </div>
            
            <div class="search-box-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="pos-search" placeholder="البحث في المنيو..." onkeyup="filterMenu()">
            </div>
        </div>

        <div class="menu-scroll-area">
            <div class="menu-grid" id="pos-grid">
                @foreach($items as $item)
                <div class="item-card" id="card-{{ $item->id }}" data-cat="{{ $item->category_id }}" data-search="{{ $item->name_ar }} {{ $item->name_en }}" onclick="addToCart({{ json_encode($item) }})">
                    <div class="card-media">
                        @if($item->image)
                        <img src="/restaurant/uploads/{{ $item->image }}" onerror="this.src='/restaurant/assets/img/placeholder.png'">
                        @else
                        <div style="position:absolute; top:0; left:0; width:100%; height:100%; background:#f8fafc; display:flex; align-items:center; justify-content:center"><i class="fas fa-hamburger fa-2x" style="color:#eee"></i></div>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="card-info-btn" onclick="showItemInfo({{ json_encode($item) }}, event)"><i class="fas fa-info-circle"></i></div>
                        <span class="card-title">{{ $item->name_ar }}</span>
                        <span class="card-subtitle">{{ $item->name_en }}</span>
                        <span class="card-price">{{ number_format($item->price, 0) }} ريال</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </main>
</div>

{{-- Info Modal --}}
<div class="desc-modal" id="info-modal" onclick="closeInfoModal()">
    <div class="desc-card" onclick="event.stopPropagation()">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px">
            <h4 id="info-title" style="margin:0; font-weight:900; color:#2c3e50"></h4>
            <button onclick="closeInfoModal()" style="background:none; border:none; font-size:1.5rem; color:#ccc; cursor:pointer">&times;</button>
        </div>
        <div id="info-desc" style="color:#718096; line-height:1.6; font-size:0.95rem; min-height:100px; text-align:right; direction:rtl"></div>
        <div style="margin-top:25px; text-align:left">
            <button onclick="closeInfoModal()" style="padding:10px 25px; border-radius:10px; background:#e67e22; color:#fff; border:none; font-weight:800; cursor:pointer">إغلاق</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let cart = [];
let paymentMethod = 'cash';
let assignedCashierId = {{ $cashiers->first()->id ?? 0 }};

function setCat(id, el) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    filterMenu();
}

function filterMenu() {
    const q = document.getElementById('pos-search').value.toLowerCase();
    const cat = document.querySelector('.cat-pill.active').getAttribute('data-id');

    document.querySelectorAll('.item-card').forEach(card => {
        const name = card.getAttribute('data-search').toLowerCase();
        const itemCat = card.getAttribute('data-cat');
        const matchesCat = (cat === 'all' || itemCat === cat);
        const matchesSearch = name.includes(q);
        card.style.display = (matchesCat && matchesSearch) ? 'flex' : 'none';
    });
}

function showItemInfo(item, e) {
    e.stopPropagation();
    document.getElementById('info-title').textContent = item.name_ar;
    document.getElementById('info-desc').textContent = item.description || 'لا يوجد وصف متاح لهذا الصنف حالياً.';
    document.getElementById('info-modal').style.display = 'flex';
}

function closeInfoModal() {
    document.getElementById('info-modal').style.display = 'none';
}

function setPayMethod(m) {
    paymentMethod = m;
    document.getElementById('pay-cash').classList.toggle('active', m === 'cash');
    document.getElementById('pay-wallet').classList.toggle('active', m === 'wallet');
}

function setCashier(id, el) {
    assignedCashierId = id;
    document.querySelectorAll('.cashier-opt').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
}

function addToCart(item) {
    const exists = cart.find(i => i.id === item.id);
    if (exists) {
        exists.quantity++;
    } else {
        cart.push({ id: item.id, name_ar: item.name_ar, price: parseFloat(item.price), quantity: 1 });
    }
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cart-list');
    
    // UI Badges
    document.querySelectorAll('.item-card').forEach(card => {
        const id = card.id.replace('card-', '');
        const count = cart.filter(c => String(c.id) === id).reduce((s, c) => s + c.quantity, 0);
        let badge = card.querySelector('.item-qty-badge');
        if (count > 0) {
            card.classList.add('selected');
            if (!badge) { badge = document.createElement('div'); badge.className = 'item-qty-badge'; card.appendChild(badge); }
            badge.textContent = count;
        } else {
            card.classList.remove('selected');
            if (badge) badge.remove();
        }
    });

    if (cart.length === 0) {
        list.innerHTML = `<div style="text-align:center; padding:50px 20px; color:#cbd5e0"><i class="fas fa-cart-plus fa-3x" style="opacity:0.2; margin-bottom:10px"></i><p style="font-weight:700">السلة فارغة</p></div>`;
        document.getElementById('cart-total').textContent = '0.00 ريال';
        return;
    }

    let total = 0;
    list.innerHTML = cart.map((item, idx) => {
        const sub = item.price * item.quantity;
        total += sub;
        return `
            <div class="cart-item">
                <div class="cart-item-info">
                    <span class="cart-item-name">${item.name_ar}</span>
                    <span class="cart-item-sub">${sub.toLocaleString()} ريال</span>
                </div>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="updateQty(${idx}, -1)">-</button>
                    <span style="font-weight:900; font-size:1rem; min-width:15px; text-align:center">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQty(${idx}, 1)">+</button>
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('cart-total').textContent = total.toLocaleString() + ' ريال';
}

function updateQty(idx, delta) {
    cart[idx].quantity += delta;
    if (cart[idx].quantity <= 0) cart.splice(idx, 1);
    renderCart();
}

function clearCart() { if (confirm('هل أنت متأكد من مسح السلة؟')) { cart = []; renderCart(); } }

async function submitOrder() {
    const table = document.getElementById('table-number').value;
    if (!table) { alert('يرجى إدخال رقم الطاولة'); return; }
    if (cart.length === 0) { alert('السلة فارغة'); return; }

    const btn = document.querySelector('.btn-send-order');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';

    const payload = {
        table_number: table,
        cashier_id: assignedCashierId,
        payment_method: paymentMethod,
        notes: document.getElementById('order-notes').value,
        items: cart
    };

    try {
        const res = await fetch('{{ route("waiter.orders.store") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(payload)
        }).then(r => r.json());

        if (res.success) {
            alert('تم إرسال الطلب بنجاح ✅');
            cart = [];
            renderCart();
            document.getElementById('table-number').value = '';
            document.getElementById('order-notes').value = '';
        } else {
            alert(res.message || 'حدث خطأ');
        }
    } catch (e) { alert('فشل الاتصال بالسيرفر'); }
    
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال الطلب';
}
</script>
@endpush
