@extends('layouts.admin')
@section('title', 'العروض والتخفيضات')

@push('styles')
<style>
    .tabs-container { display: flex; gap: 5px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
    .tab-btn {
        padding: 12px 20px; border: none; background: none; color: #777;
        font-weight: 600; cursor: pointer; border-radius: 8px 8px 0 0;
        transition: all 0.2s; display: flex; align-items: center; gap: 8px;
        font-size: 0.95rem; border-bottom: 3px solid transparent;
    }
    .tab-btn:hover { background: rgba(0,0,0,0.02); }
    .tab-btn.active {
        color: var(--primary); border-bottom-color: var(--primary);
        background: rgba(230,126,34,0.05);
    }
    .tab-section { animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endpush

@section('content')

<div class="card">
    <div class="card-header" style="padding-bottom:0; display:block">
        <div class="tabs-container">
            <button class="tab-btn active" id="tab-combos" onclick="switchTab('combos')">
                <i class="fas fa-box-open"></i> الباقات (Combos)
            </button>
            <button class="tab-btn" id="tab-items" onclick="switchTab('items')">
                <i class="fas fa-tag"></i> تخفيضات الأصناف
            </button>
            <button class="tab-btn" id="tab-categories" onclick="switchTab('categories')">
                <i class="fas fa-folder-open"></i> تخفيضات الفئات
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        
        {{-- TAB: COMBOS --}}
        <div id="section-combos" class="tab-section">
            <div style="padding:15px; border-bottom:1px solid #eee; background:#fafafa; display:flex; justify-content:space-between; align-items:center">
                <span style="color:#666; font-size:.9rem">إدارة باقات الوجبات والعروض المجمعة</span>
                <button class="btn btn-primary btn-sm" onclick="openComboModal()">
                    <i class="fas fa-plus"></i> باقة جديدة
                </button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>اسم الباقة</th>
                            <th>السعر</th>
                            <th>محتويات الباقة</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($combos as $c)
                        <tr>
                            <td><strong>{{ $c->name_ar }}</strong></td>
                            <td style="color:var(--primary); font-weight:800">{{ number_format($c->price, 2) }}</td>
                            <td>
                                <ul style="margin:0; padding-right:15px; font-size:0.85rem; color:#555">
                                    @foreach($c->items as $ci)
                                    <li>{{ (int)$ci->quantity }}x {{ $ci->item_name }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                <span class="badge badge-{{ $c->is_active ? 'success' : 'danger' }}">
                                    {{ $c->is_active ? 'نشط' : 'متوقف' }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:5px">
                                    <form action="{{ route('admin.offers.combo.toggle', $c->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $c->is_active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm btn-outline">{{ $c->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                    <form action="{{ route('admin.offers.combo.destroy', $c->id) }}" method="POST" onsubmit="return confirm('حذف الباقة؟')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#999">لا توجد باقات حالياً</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB: ITEM DISCOUNTS --}}
        <div id="section-items" class="tab-section" style="display:none">
            <div style="padding:15px; border-bottom:1px solid #eee; background:#fafafa; display:flex; justify-content:space-between; align-items:center">
                <span style="color:#666; font-size:.9rem">تطبيق خصومات مباشرة على أصناف محددة</span>
                <button class="btn btn-primary btn-sm" onclick="openDiscountModal('item')">
                    <i class="fas fa-plus"></i> تخفيض جديد لصنف
                </button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>الصنف المستهدف</th>
                            <th>نوع التخفيض</th>
                            <th>القيمة</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discounts->where('type', 'item') as $d)
                        <tr>
                            <td><strong>{{ $d->target_name }}</strong></td>
                            <td>{{ $d->discount_type == 'percent' ? 'نسبة مئوية' : 'مبلغ ثابت' }}</td>
                            <td style="color:var(--danger); font-weight:800">-{{ $d->discount_value }} {{ $d->discount_type == 'percent' ? '%' : 'ريال' }}</td>
                            <td><span class="badge badge-{{ $d->is_active ? 'success' : 'danger' }}">{{ $d->is_active ? 'نشط' : 'متوقف' }}</span></td>
                            <td>
                                <div style="display:flex; gap:5px">
                                    <form action="{{ route('admin.offers.discount.toggle', $d->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $d->is_active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm btn-outline">{{ $d->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                    <form action="{{ route('admin.offers.discount.destroy', $d->id) }}" method="POST" onsubmit="return confirm('حذف؟')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#999">لا توجد تخفيضات أصناف</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB: CATEGORY DISCOUNTS --}}
        <div id="section-categories" class="tab-section" style="display:none">
            <div style="padding:15px; border-bottom:1px solid #eee; background:#fafafa; display:flex; justify-content:space-between; align-items:center">
                <span style="color:#666; font-size:.9rem">تطبيق خصم مئوي أو ثابت على فئة كاملة</span>
                <button class="btn btn-primary btn-sm" onclick="openDiscountModal('category')">
                    <i class="fas fa-plus"></i> تخفيض جديد لفئة
                </button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>الفئة المستهدفة</th>
                            <th>نوع التخفيض</th>
                            <th>القيمة</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($discounts->where('type', 'category') as $d)
                        <tr>
                            <td><strong>{{ $d->target_name }}</strong></td>
                            <td>{{ $d->discount_type == 'percent' ? 'نسبة مئوية' : 'مبلغ ثابت' }}</td>
                            <td style="color:var(--danger); font-weight:800">-{{ $d->discount_value }} {{ $d->discount_type == 'percent' ? '%' : 'ريال' }}</td>
                            <td><span class="badge badge-{{ $d->is_active ? 'success' : 'danger' }}">{{ $d->is_active ? 'نشط' : 'متوقف' }}</span></td>
                            <td>
                                <div style="display:flex; gap:5px">
                                    <form action="{{ route('admin.offers.discount.toggle', $d->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $d->is_active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm btn-outline">{{ $d->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                                    </form>
                                    <form action="{{ route('admin.offers.discount.destroy', $d->id) }}" method="POST" onsubmit="return confirm('حذف؟')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:#999">لا توجد تخفيضات فئات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- Modal: Combo --}}
<div class="modal-backdrop hidden" id="combo-modal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3>إضافة باقة جديدة</h3>
            <button class="btn-close" onclick="closeComboModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="combo-form" action="{{ route('admin.offers.combo.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>اسم الباقة (مثال: وجبة التوفير)</label>
                    <input type="text" name="name_ar" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>سعر الباقة النهائي (ريال)</label>
                    <input type="number" name="price" class="form-control" step="0.01" required>
                </div>
                <div style="background:#f9f9f9; padding:15px; border-radius:12px; border:1px dashed #ddd">
                    <label style="font-weight:700; margin-bottom:10px; display:block">محتويات الباقة</label>
                    <div style="display:grid; grid-template-columns:1fr 80px 50px; gap:8px; margin-bottom:12px">
                        <select id="sel-item" class="form-control">
                            @foreach($items as $i)
                            <option value="{{ $i->id }}">{{ $i->name_ar }}</option>
                            @endforeach
                        </select>
                        <input type="number" id="sel-qty" class="form-control" value="1" min="1">
                        <button type="button" class="btn btn-secondary" onclick="addComboItem()"><i class="fas fa-plus"></i></button>
                    </div>
                    <div id="combo-items-list">
                        {{-- Added items go here --}}
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeComboModal()">إلغاء</button>
            <button class="btn btn-primary" onclick="document.getElementById('combo-form').submit()">حفظ الباقة</button>
        </div>
    </div>
</div>

{{-- Modal: Discount --}}
<div class="modal-backdrop hidden" id="discount-modal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3 id="d-title">إضافة تخفيض</h3>
            <button class="btn-close" onclick="closeDiscountModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="discount-form" action="{{ route('admin.offers.discount.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" id="d-type">
                <div class="form-group">
                    <label id="d-label">العنصر المستهدف</label>
                    <select name="target_id" id="d-target" class="form-control" required></select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>نوع الخصم</label>
                        <select name="discount_type" class="form-control">
                            <option value="fixed">مبلغ ثابت (ريال)</option>
                            <option value="percent">نسبة مئوية (%)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>قيمة الخصم</label>
                        <input type="number" name="discount_value" class="form-control" step="0.01" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeDiscountModal()">إلغاء</button>
            <button class="btn btn-primary" onclick="document.getElementById('discount-form').submit()">حفظ</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let itemsData = @json($items);
let categoriesData = @json($categories);

function switchTab(tabId) {
    document.querySelectorAll('.tab-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + tabId).style.display = 'block';
    document.getElementById('tab-' + tabId).classList.add('active');
}

function openComboModal() {
    document.getElementById('combo-items-list').innerHTML = '';
    document.getElementById('combo-modal').classList.remove('hidden');
}
function closeComboModal() { document.getElementById('combo-modal').classList.add('hidden'); }

function addComboItem() {
    const sel = document.getElementById('sel-item');
    const id = sel.value;
    const name = sel.options[sel.selectedIndex].text;
    const qty = document.getElementById('sel-qty').value;
    
    const html = `
        <div class="combo-item-row" style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:8px 12px; border-radius:8px; margin-bottom:5px; border:1px solid #eee">
            <input type="hidden" name="items[${id}][id]" value="${id}">
            <input type="hidden" name="items[${id}][qty]" value="${qty}">
            <span>${name}</span>
            <span class="badge badge-info">${qty}x</span>
            <button type="button" class="btn-close" style="font-size:0.8rem" onclick="this.parentElement.remove()">✕</button>
        </div>
    `;
    document.getElementById('combo-items-list').insertAdjacentHTML('beforeend', html);
}

function openDiscountModal(type) {
    document.getElementById('d-type').value = type;
    document.getElementById('d-title').textContent = type === 'item' ? 'إضافة تخفيض لصنف' : 'إضافة تخفيض لفئة كاملة';
    document.getElementById('d-label').textContent = type === 'item' ? 'اختر الصنف' : 'اختر الفئة';
    
    const sel = document.getElementById('d-target');
    sel.innerHTML = '';
    const data = type === 'item' ? itemsData : categoriesData;
    data.forEach(d => {
        sel.innerHTML += `<option value="${d.id}">${d.name_ar}</option>`;
    });
    
    document.getElementById('discount-modal').classList.remove('hidden');
}
function closeDiscountModal() { document.getElementById('discount-modal').classList.add('hidden'); }
</script>
@endpush
