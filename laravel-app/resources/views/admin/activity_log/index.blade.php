@extends('layouts.admin')
@section('title', 'مراقبة النظام')

@push('styles')
<style>
    .pagination {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
        list-style: none !important;
        padding: 0 !important;
        margin: 20px 0 !important;
    }
    .pagination li {
        list-style: none !important;
        margin: 0 2px !important;
        display: block !important;
    }
    .pagination li a, .pagination li span {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 35px !important;
        height: 35px !important;
        padding: 0 10px !important;
        border: 1px solid #ddd !important;
        border-radius: 6px !important;
        background: #fff !important;
        color: #333 !important;
        text-decoration: none !important;
        font-weight: bold !important;
    }
    .pagination li.active span {
        background: var(--primary, #e67e22) !important;
        color: #fff !important;
        border-color: var(--primary, #e67e22) !important;
    }
    .pagination li.disabled span {
        color: #ccc !important;
        background: #f9f9f9 !important;
    }
</style>
@endpush

@section('content')
<div class="card mb-20">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 style="margin:0"><i class="fas fa-history"></i> مراقبة النظام ({{ is_object($logs) && method_exists($logs, 'total') ? $logs->total() : count($logs) }})</h3>
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
            <select name="user_id" class="form-control" style="width:200px">
                <option value="">كل المستخدمين</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }} ({{ $user->username }})</option>
                @endforeach
            </select>
            <input type="text" name="action" class="form-control" placeholder="نوع الإجراء..." value="{{ request('action') }}" style="width:200px">
            <input type="date" name="date" class="form-control" value="{{ request('date') }}" style="width:200px">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> بحث</button>
            <a href="{{ url()->current() }}" class="btn btn-secondary">إعادة ضبط</a>
        </form>

        <div class="table-responsive">
            <table style="width: 100%; text-align: right;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                        <th style="padding: 12px;">#</th>
                        <th style="padding: 12px;">المستخدم</th>
                        <th style="padding: 12px;">الإجراء</th>
                        <th style="padding: 12px;">التفاصيل</th>
                        <th style="padding: 12px;">التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;">{{ $log->id }}</td>
                            <td style="padding: 12px;"><strong>{{ $log->user_name ?? 'نظام' }}</strong> <small style="color: #888;">({{ $log->username ?? '-' }})</small></td>
                            <td style="padding: 12px;"><span class="badge badge-info">{{ $log->action }}</span></td>
                            <td style="padding: 12px;">{{ $log->details }}</td>
                            <td style="padding: 12px; direction: ltr; text-align: right; color: #666; font-size: 0.9rem;">
                                {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #888;">لا توجد سجلات مطابقة للبحث.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; display: flex; justify-content: center;">
            {{ is_object($logs) && method_exists($logs, 'links') ? $logs->appends(['limit' => request('limit')])->links() : '' }}
        </div>
    </div>
</div>
@endsection
