@extends('layouts.admin')
@section('title', 'إدارة المكونات')

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin:0"><i class="fas fa-cubes"></i> إدارة المكونات ({{ $ingredients->total() }})</h3>
        <div style="display:flex; align-items:center; gap:15px">
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
            <button class="btn btn-primary btn-sm" onclick="openIngredientModal()"><i class="fas fa-plus"></i> إضافة مكون جديد</button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" style="margin: 0;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>#</th>
                        <th>اسم المكون</th>
                        <th>الوحدة</th>
                        <th>الحد الأدنى للمخزون</th>
                        <th>الكمية الحالية</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ingredients as $ingredient)
                        <tr>
                            <td>{{ $ingredient->id }}</td>
                            <td><strong>{{ $ingredient->name }}</strong></td>
                            <td><span class="badge badge-info">{{ $ingredient->unit }}</span></td>
                            <td style="text-align: center; color: #e74c3c;">{{ $ingredient->min_stock }}</td>
                            <td style="text-align: center; font-weight: bold; color: {{ $ingredient->current_stock <= $ingredient->min_stock ? '#e74c3c' : '#27ae60' }}">
                                {{ $ingredient->current_stock }}
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick='editIngredient(@json($ingredient))'><i class="fas fa-edit"></i></button>
                                <form action="{{ route('admin.ingredients.destroy', $ingredient->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف المكون؟');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #888;">لا توجد مكونات مضافة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div style="padding:15px; display:flex; justify-content:center">
        {{ $ingredients->appends(['limit' => request('limit')])->links() }}
    </div>
</div>

{{-- Add/Edit Ingredient Modal --}}
<div class="modal-backdrop hidden" id="ingredient-modal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="modal-title">إضافة مكون</h3>
            <button class="modal-close" onclick="closeIngredientModal()">✕</button>
        </div>
        <form id="ingredient-form" action="{{ route('admin.ingredients.store') }}" method="POST">
            @csrf
            <div id="method-container"></div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">اسم المكون *</label>
                    <input type="text" name="name" id="ing_name" class="form-control" required style="width: 100%;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">الوحدة *</label>
                    <select name="unit" id="ing_unit" class="form-control" required style="width: 100%;">
                        <option value="">-- اختر الوحدة --</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->name }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; margin-bottom: 5px;">الحد الأدنى للمخزون (للتنبيهات)</label>
                    <input type="number" step="0.01" name="min_stock" id="ing_min_stock" class="form-control" value="0" style="width: 100%; direction: ltr;">
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-secondary" onclick="closeIngredientModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openIngredientModal() {
        document.getElementById('modal-title').innerText = 'إضافة مكون جديد';
        document.getElementById('ingredient-form').action = "{{ route('admin.ingredients.store') }}";
        document.getElementById('method-container').innerHTML = '';
        
        document.getElementById('ing_name').value = '';
        document.getElementById('ing_unit').value = '';
        document.getElementById('ing_min_stock').value = '0';
        document.getElementById('ing_cost').value = '0';
        
        document.getElementById('ingredient-modal').classList.remove('hidden');
    }
    
    function editIngredient(ing) {
        document.getElementById('modal-title').innerText = 'تعديل مكون';
        document.getElementById('ingredient-form').action = "{{ url('admin/ingredients') }}/" + ing.id + "/update";
        
        document.getElementById('ing_name').value = ing.name;
        document.getElementById('ing_unit').value = ing.unit;
        document.getElementById('ing_min_stock').value = ing.min_stock;
        document.getElementById('ing_cost').value = ing.cost_per_unit;
        
        document.getElementById('ingredient-modal').classList.remove('hidden');
    }
    
    function closeIngredientModal() {
        document.getElementById('ingredient-modal').classList.add('hidden');
    }
</script>
@endpush
