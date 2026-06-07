@extends('layouts.admin')
@section('title', 'إدارة الوحدات')

@section('content')
<div class="card mb-20">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 style="margin:0"><i class="fas fa-balance-scale"></i> الوحدات ({{ $units->total() }})</h3>
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
            <button class="btn btn-primary" onclick="openUnitModal()"><i class="fas fa-plus"></i> إضافة وحدة</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم الوحدة</th>
                        <th>تاريخ الإضافة</th>
                        <th>العمليات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($units as $unit)
                    <tr>
                        <td>{{ $unit->id }}</td>
                        <td><strong>{{ $unit->name }}</strong></td>
                        <td>{{ date('Y-m-d', strtotime($unit->created_at)) }}</td>
                        <td>
                            <div style="display:flex; gap:5px">
                                <button class="btn btn-info btn-sm" onclick="editUnit({{ json_encode($unit) }})"><i class="fas fa-edit"></i></button>
                                <form action="{{ route('admin.units.destroy', $unit->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الوحدة؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted)">لا توجد وحدات مضافة حالياً.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:20px; display:flex; justify-content:center">
            {{ $units->appends(['limit' => request('limit')])->links() }}
        </div>
    </div>
</div>

<!-- Modal -->
<div id="unit-modal" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">إضافة وحدة جديدة</h3>
            <button class="btn-close" onclick="closeUnitModal()">&times;</button>
        </div>
        <form id="unit-form" action="{{ route('admin.units.store') }}" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>اسم الوحدة</label>
                    <input type="text" name="name" id="u-name" class="form-control" required placeholder="مثال: كيلو، لتر، حبة...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeUnitModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ البيانات</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUnitModal() {
        document.getElementById('modal-title').innerText = 'إضافة وحدة جديدة';
        document.getElementById('unit-form').action = "{{ route('admin.units.store') }}";
        document.getElementById('form-method').value = 'POST';
        document.getElementById('u-name').value = '';
        document.getElementById('unit-modal').classList.remove('hidden');
    }

    function closeUnitModal() {
        document.getElementById('unit-modal').classList.add('hidden');
    }

    function editUnit(unit) {
        document.getElementById('modal-title').innerText = 'تعديل الوحدة';
        document.getElementById('unit-form').action = `/restaurant/laravel-app/public/admin/units/${unit.id}`;
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('u-name').value = unit.name;
        document.getElementById('unit-modal').classList.remove('hidden');
    }
</script>
@endsection
