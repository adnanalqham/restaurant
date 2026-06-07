@extends('layouts.admin')
@section('title', 'إدارة الفئات')
@section('content')

@section('topbar-actions')
    <button class="btn btn-primary" onclick="openAdd()"><i class="fas fa-plus"></i> إضافة فئة</button>
@endsection

{{-- Categories Table --}}
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 style="margin:0"><i class="fas fa-folder"></i> الفئات ({{ $categories->total() }})</h3>
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
    <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>الأيقونة</th><th>الاسم بالعربية</th><th>الاسم بالإنجليزية</th><th>الترتيب</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($categories as $cat)
            <tr>
                <td>{{ $cat->id }}</td>
                <td style="text-align:center"><i class="{{ $cat->icon }} fa-lg" style="color:var(--primary)"></i></td>
                <td><strong>{{ $cat->name_ar }}</strong></td>
                <td>{{ $cat->name_en }}</td>
                <td style="text-align:center">{{ $cat->sort_order }}</td>
                <td style="text-align:center">
                    @if($cat->is_active)
                        <span class="badge badge-success">نشط</span>
                    @else
                        <span class="badge badge-danger">متوقف</span>
                    @endif
                </td>
                <td style="display:flex;gap:6px">
                    <button class="btn btn-outline btn-sm" onclick="openEdit({{ $cat->id }}, '{{ addslashes($cat->name_ar) }}', '{{ addslashes($cat->name_en) }}', {{ $cat->sort_order }}, '{{ $cat->icon }}', {{ $cat->is_active }})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}" onsubmit="return confirm('حذف الفئة؟')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">لا توجد فئات</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:15px; display:flex; justify-content:center">
        {{ $categories->appends(['limit' => request('limit')])->links() }}
    </div>
</div>

{{-- Add/Edit Modal --}}
<div class="modal-backdrop hidden" id="item-modal">
    <div class="modal" style="width:480px;max-width:95vw">
        <div class="modal-header">
            <h3 id="modal-title">إضافة فئة</h3>
            <button class="btn-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="categoryForm">
                @csrf
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px">
                    <div class="form-group">
                        <label>الاسم بالعربية *</label>
                        <input type="text" name="name_ar" id="cat_name_ar" class="form-control" required placeholder="مثال: وجبات سريعة">
                    </div>
                    <div class="form-group">
                        <label>الاسم بالإنجليزية</label>
                        <input type="text" name="name_en" id="cat_name_en" class="form-control" placeholder="Fast Food">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:10px">
                    <div class="form-group">
                        <label>الأيقونة (Font Awesome)</label>
                        <input type="text" name="icon" id="cat_icon" class="form-control" placeholder="fas fa-utensils">
                    </div>
                    <div class="form-group">
                        <label>الترتيب</label>
                        <input type="number" name="sort_order" id="cat_sort_order" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group" style="margin-top:15px">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                        <input type="checkbox" name="is_active" id="cat_is_active" value="1" checked>
                        <span>الفئة نشطة وتظهر في القائمة</span>
                    </label>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px">
                    <button type="submit" class="btn btn-primary" style="flex:1">حفظ البيانات</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal()" style="flex:1">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openAdd() {
    document.getElementById('modal-title').textContent = 'إضافة فئة جديدة';
    document.getElementById('categoryForm').action = "{{ route('admin.categories.store') }}";
    document.getElementById('cat_name_ar').value = '';
    document.getElementById('cat_name_en').value = '';
    document.getElementById('cat_icon').value = 'fas fa-utensils';
    document.getElementById('cat_sort_order').value = '0';
    document.getElementById('cat_is_active').checked = true;
    document.getElementById('item-modal').classList.remove('hidden');
}

function openEdit(id, nameAr, nameEn, sort, icon, isActive) {
    document.getElementById('modal-title').textContent = 'تعديل الفئة';
    document.getElementById('categoryForm').action = `/admin/categories/${id}/update`;
    document.getElementById('cat_name_ar').value = nameAr;
    document.getElementById('cat_name_en').value = nameEn;
    document.getElementById('cat_sort_order').value = sort;
    document.getElementById('cat_icon').value = icon || 'fas fa-utensils';
    document.getElementById('cat_is_active').checked = isActive == 1;
    document.getElementById('item-modal').classList.remove('hidden');
}

function closeModal() { 
    document.getElementById('item-modal').classList.add('hidden'); 
}
</script>
@endpush
