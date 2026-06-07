@extends('layouts.admin')
@section('title', 'مراقبة الأسعار والأصناف')

@section('content')
<div class="card mb-20">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 style="margin:0"><i class="fas fa-tags"></i> مراقبة الأسعار والأصناف ({{ $logs->total() }})</h3>
        <div style="font-size:0.85rem; color:var(--text-muted)">
            عرض 
            <select class="form-control" id="rows-limit" onchange="const u=new URL(window.location.href);u.searchParams.set('limit',this.value);u.searchParams.set('page',1);window.location.href=u.href" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.85rem">
                <option value="10" {{ request('limit') == 10 ? 'selected' : '' }}>10</option>
                <option value="20" {{ request('limit') == 20 ? 'selected' : '' }}>20</option>
                <option value="50" {{ request('limit') == 50 || !request('limit') ? 'selected' : '' }}>50</option>
                <option value="100" {{ request('limit') == 100 ? 'selected' : '' }}>100</option>
            </select>
            أسطر
        </div>
    </div>
    <div class="card-body" style="padding: 20px;">
        <form method="GET" style="display:flex; gap:10px; margin-bottom: 20px; flex-wrap: wrap;">
            <select name="action_type" class="form-control" style="width:200px">
                <option value="">كل الإجراءات</option>
                <option value="create" {{ request('action_type') == 'create' ? 'selected' : '' }}>إضافة</option>
                <option value="update" {{ request('action_type') == 'update' ? 'selected' : '' }}>تعديل</option>
                <option value="delete" {{ request('action_type') == 'delete' ? 'selected' : '' }}>حذف</option>
            </select>
            <input type="text" name="field" class="form-control" placeholder="اسم الحقل (مثال: price)..." value="{{ request('field') }}" style="width:200px">
            <input type="date" name="date" class="form-control" value="{{ request('date') }}" style="width:200px">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> بحث</button>
            <a href="{{ url()->current() }}" class="btn btn-secondary">إعادة ضبط</a>
        </form>

        <div class="table-responsive">
            <table style="width: 100%; text-align: right;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                        <th style="padding: 12px;">#</th>
                        <th style="padding: 12px;">الصنف</th>
                        <th style="padding: 12px;">المستخدم</th>
                        <th style="padding: 12px;">الإجراء</th>
                        <th style="padding: 12px;">الحقل المعدل</th>
                        <th style="padding: 12px;">القيمة القديمة</th>
                        <th style="padding: 12px;">القيمة الجديدة</th>
                        <th style="padding: 12px;">التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;">{{ $log->id }}</td>
                            <td style="padding: 12px;"><strong>{{ $log->item_name ?? 'غير متوفر (رقم '.$log->item_id.')' }}</strong></td>
                            <td style="padding: 12px;">{{ $log->user_name ?? 'نظام' }}</td>
                            <td style="padding: 12px;">
                                @if($log->action_type == 'update') <span class="badge badge-warning">تعديل</span>
                                @elseif($log->action_type == 'create') <span class="badge badge-success">إضافة</span>
                                @elseif($log->action_type == 'delete') <span class="badge badge-danger">حذف</span>
                                @else <span class="badge badge-secondary">{{ $log->action_type }}</span>
                                @endif
                            </td>
                            <td style="padding: 12px;">
                                @if($log->field_name == 'price') <span class="badge badge-info">السعر</span>
                                @elseif($log->field_name == 'is_active') <span class="badge badge-info">الحالة</span>
                                @elseif($log->field_name == 'name_ar') <span class="badge badge-info">الاسم</span>
                                @else <code>{{ $log->field_name ?? '-' }}</code>
                                @endif
                            </td>
                            <td style="padding: 12px; direction: ltr; text-align: right; color: #e74c3c; text-decoration: line-through;">{{ $log->old_value ?? '-' }}</td>
                            <td style="padding: 12px; direction: ltr; text-align: right; color: #27ae60; font-weight: bold;">{{ $log->new_value ?? '-' }}</td>
                            <td style="padding: 12px; direction: ltr; text-align: right; color: #666; font-size: 0.9rem;">{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #888;">لا توجد سجلات مطابقة للبحث.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; display: flex; justify-content: center;">
            {{ $logs->appends(['limit' => request('limit')])->links() }}
        </div>
    </div>
</div>
@endsection
