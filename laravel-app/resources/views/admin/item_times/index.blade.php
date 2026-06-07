@extends('layouts.admin')
@section('title', 'أوقات تحضير الأصناف ⏱️')
@section('content')

@php
function formatSecToMin($seconds) {
    if (!$seconds || $seconds < 0) return "-";
    $m = floor($seconds / 60);
    $s = round($seconds % 60);
    if ($m > 0) return $m . " د و " . $s . " ث";
    return $s . " ثانية";
}
@endphp

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:24px; gap:15px">
    <div>
        <h2 style="margin:0"><i class="fas fa-stopwatch" style="color:var(--primary)"></i> أوقات تحضير الأصناف</h2>
        <p style="color:var(--text-muted); font-size:0.9rem">تتبع الوقت المستغرق في المطبخ لتجهيز الطلبات.</p>
    </div>
    <form method="GET">
        <select name="filter" class="form-control" onchange="this.form.submit()" style="min-width:150px">
            <option value="today" {{ $filter == 'today' ? 'selected' : '' }}>اليوم</option>
            <option value="7days" {{ $filter == '7days' ? 'selected' : '' }}>آخر 7 أيام</option>
            <option value="30days" {{ $filter == '30days' ? 'selected' : '' }}>آخر 30 يوم</option>
            <option value="all" {{ $filter == 'all' ? 'selected' : '' }}>كل الأوقات</option>
        </select>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th style="text-align:center">مرات التحضير</th>
                    <th style="text-align:center">متوسط الوقت <i class="fas fa-clock text-primary"></i></th>
                    <th style="text-align:center">أسرع وقت <i class="fas fa-bolt text-success"></i></th>
                    <th style="text-align:center">أبطأ وقت <i class="fas fa-hourglass-end text-danger"></i></th>
                </tr>
            </thead>
            <tbody>
                @forelse($itemStats as $stat)
                <tr>
                    <td><strong>{{ $stat->name_ar }}</strong></td>
                    <td style="text-align:center"><span class="badge badge-secondary">{{ $stat->times_prepared }}</span></td>
                    <td style="text-align:center; color:var(--primary); font-weight:700">{{ formatSecToMin($stat->avg_time_sec) }}</td>
                    <td style="text-align:center; color:var(--success)">{{ formatSecToMin($stat->min_time_sec) }}</td>
                    <td style="text-align:center; color:var(--danger)">{{ formatSecToMin($stat->max_time_sec) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:50px; color:#999">لا توجد بيانات لهذه الفترة.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
