@extends('layouts.admin')
@section('title', 'إدخال المخزون (المشتريات)')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px; align-items: start;">
    {{-- Purchases List --}}
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
            <h3 style="margin:0"><i class="fas fa-shopping-cart"></i> سجل المشتريات ({{ $purchases->total() }})</h3>
            <div style="font-size:0.85rem; color:var(--text-muted)">
                عرض 
                <select class="form-control" id="rows-limit" onchange="const u=new URL(window.location.href);u.searchParams.set('limit',this.value);u.searchParams.set('page',1);window.location.href=u.href" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.85rem">
                    <option value="10" {{ request('limit') == 10 ? 'selected' : '' }}>10</option>
                    <option value="20" {{ request('limit') == 20 ? 'selected' : '' }}>20</option>
                    <option value="50" {{ request('limit') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('limit') == 100 ? 'selected' : '' }}>100</option>
                </select>
                أسطر
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" style="margin: 0;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th>#</th>
                            <th>الصنف</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>المورد</th>
                            <th>بواسطة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                            <tr>
                                <td>{{ $purchase->id }}</td>
                                <td><strong>{{ $purchase->item_name }}</strong></td>
                                <td style="text-align: center;">{{ $purchase->quantity }} <small>{{ $purchase->unit }}</small></td>
                                <td style="text-align: center;">{{ number_format($purchase->price, 2) }}</td>
                                <td>{{ $purchase->supplier ?? '-' }}</td>
                                <td>{{ $purchase->user_name }}</td>
                                <td style="direction: ltr; text-align: right; font-size: 0.9rem;">{{ \Carbon\Carbon::parse($purchase->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #888;">لا توجد مشتريات مسجلة بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div style="padding:15px; display:flex; justify-content:center">
            {{ $purchases->appends(['limit' => request('limit')])->links() }}
        </div>
    </div>

    {{-- Add Purchase Form --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> تسجيل شراء</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.inventory.purchases.store') }}" method="POST">
                @csrf
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">المكون / الصنف *</label>
                    <select name="item_id" class="form-control" required style="width: 100%;">
                        <option value="">-- اختر --</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }}) - الكمية الحالية: {{ $item->current_stock }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">الكمية المشتراة *</label>
                    <input type="number" step="0.01" name="quantity" class="form-control" required min="0.01" style="width: 100%; direction: ltr;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">سعر الوحدة *</label>
                    <input type="number" step="0.01" name="price" class="form-control" required min="0" style="width: 100%; direction: ltr;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">اسم المورد (اختياري)</label>
                    <input type="text" name="supplier" class="form-control" style="width: 100%;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3" style="width: 100%;"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> تسجيل وتحديث المخزون</button>
            </form>
            
            <hr style="margin: 20px 0; border-color: #eee;">
            
            <p style="font-size: 0.85rem; color: #666; text-align: center; margin-bottom: 10px;">الصنف غير موجود؟</p>
            <button type="button" class="btn btn-secondary" style="width: 100%;" onclick="openItemModal()"><i class="fas fa-cube"></i> إضافة صنف جديد للمخزن</button>
        </div>
    </div>
</div>

{{-- Add Item Modal --}}
<div class="modal-backdrop hidden" id="item-modal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>إضافة صنف جديد</h3>
            <button class="modal-close" onclick="closeItemModal()">✕</button>
        </div>
        <form action="{{ route('admin.inventory.items.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label">الاسم *</label>
                    <input type="text" name="name" class="form-control" required style="width: 100%;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label">الوحدة *</label>
                    <input type="text" name="unit" class="form-control" required style="width: 100%;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label">الحد الأدنى</label>
                    <input type="number" step="0.01" name="min_stock" class="form-control" value="0" style="width: 100%;">
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeItemModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openItemModal() { document.getElementById('item-modal').classList.remove('hidden'); }
    function closeItemModal() { document.getElementById('item-modal').classList.add('hidden'); }
</script>
@endpush
