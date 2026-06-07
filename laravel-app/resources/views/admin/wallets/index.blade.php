@extends('layouts.admin')
@section('title', 'إدارة المحافظ الرقمية')
@section('content')

    {{-- Add Form --}}
    <div class="card mb-16">
        <div class="card-header">
            <h3><i class="fas fa-plus"></i> إضافة محفظة جديدة</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.wallets.store') }}"
                style="display:grid; grid-template-columns: 1fr 1fr auto auto; gap:12px; align-items:end">
                @csrf
                <div class="form-group" style="margin:0">
                    <label>اسم المحفظة *</label>
                    <input type="text" name="name" class="form-control" placeholder="مثلاً: جيب" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label>رقم الحساب / النقطة *</label>
                    <input type="text" name="account_number" class="form-control" placeholder="123456" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label>الترتيب</label>
                    <input type="number" name="sort_order" class="form-control" value="0" style="width:80px">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة</button>
            </form>
        </div>
    </div>

    {{-- Wallets Table --}}
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
            <h3 style="margin:0">المحافظ الرقمية ({{ $wallets->total() }})</h3>
            <div style="font-size:0.85rem; color:var(--text-muted)">
                عرض
                <select class="form-control" id="rows-limit"
                    onchange="const u=new URL(window.location.href);u.searchParams.set('limit',this.value);u.searchParams.set('page',1);window.location.href=u.href"
                    style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.85rem">
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
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم المحفظة</th>
                        <th>رقم الحساب / النقطة</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wallets as $wallet)
                        <tr style="{{ $wallet->is_active ? '' : 'opacity:0.6' }}">
                            <td>{{ $wallet->id }}</td>
                            <td><strong><i class="fas fa-wallet" style="color:var(--primary)"></i> {{ $wallet->name }}</strong>
                            </td>
                            <td><code>{{ $wallet->account_number }}</code></td>
                            <td>{{ $wallet->sort_order }}</td>
                            <td><span
                                    class="badge badge-{{ $wallet->is_active ? 'success' : 'danger' }}">{{ $wallet->is_active ? 'نشط' : 'معطل' }}</span>
                            </td>
                            <td style="display:flex; gap:6px">
                                <button class="btn btn-outline btn-sm"
                                    onclick="openEdit({{ $wallet->id }}, '{{ addslashes($wallet->name) }}', '{{ addslashes($wallet->account_number) }}', {{ $wallet->sort_order }}, {{ $wallet->is_active }})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.wallets.destroy', $wallet->id) }}"
                                    onsubmit="return confirm('حذف المحفظة؟')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted)">لا توجد محافظ رقمية
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:15px; display:flex; justify-content:center">
            {{ $wallets->appends(['limit' => request('limit')])->links() }}
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="editModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center">
        <div class="card" style="width:420px; max-width:95vw">
            <div class="card-header">
                <h3>تعديل المحفظة</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="editForm">
                    @csrf
                    <div class="form-group"><label>اسم المحفظة *</label><input type="text" name="name" id="edit_name"
                            class="form-control" required></div>
                    <div class="form-group"><label>رقم الحساب / النقطة *</label><input type="text" name="account_number"
                            id="edit_number" class="form-control" required></div>
                    <div class="form-group"><label>الترتيب</label><input type="number" name="sort_order" id="edit_sort"
                            class="form-control"></div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                            <input type="checkbox" name="is_active" id="edit_active" value="1">
                            <span>محفظة نشطة</span>
                        </label>
                    </div>
                    <div style="display:flex; gap:8px; margin-top:12px">
                        <button type="submit" class="btn btn-primary">حفظ</button>
                        <button type="button" class="btn btn-outline" onclick="closeEdit()">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function openEdit(id, name, number, sort, active) {
            document.getElementById('editForm').action = `/restaurant/laravel-app/public/admin/wallets/${id}/update`;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_number').value = number;
            document.getElementById('edit_sort').value = sort;
            document.getElementById('edit_active').checked = !!active;
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEdit() { document.getElementById('editModal').style.display = 'none'; }
    </script>
@endpush