@extends('layouts.admin')
@section('title', 'إحصائيات المبيعات')

@section('content')
<div class="card mb-20">
    <div class="card-header">
        <h3><i class="fas fa-chart-line"></i> إحصائيات المبيعات</h3>
    </div>
    <div class="card-body">
        <form method="GET" style="display:flex; gap:10px; margin-bottom: 20px; flex-wrap: wrap;">
            <label style="line-height: 38px; margin: 0;">من:</label>
            <input type="date" name="from" class="form-control" value="{{ $from }}" style="width:200px">
            <label style="line-height: 38px; margin: 0;">إلى:</label>
            <input type="date" name="to" class="form-control" value="{{ $to }}" style="width:200px">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> تصفية</button>
            <a href="{{ url()->current() }}" class="btn btn-secondary">الفترة الافتراضية</a>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    {{-- Sales by Category --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-layer-group"></i> المبيعات حسب الفئة</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" style="margin: 0;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th>الفئة</th>
                            <th style="text-align: center;">الكمية المباعة</th>
                            <th style="text-align: center;">الإيرادات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalCatQty = 0; $totalCatRev = 0; @endphp
                        @forelse($byCategory as $cat)
                            @php $totalCatQty += $cat->qty; $totalCatRev += $cat->revenue; @endphp
                            <tr>
                                <td><strong>{{ $cat->name_ar }}</strong></td>
                                <td style="text-align: center;">{{ $cat->qty }}</td>
                                <td style="text-align: center; color: var(--primary); font-weight: bold;">{{ number_format($cat->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align: center; color: #888; padding: 20px;">لا توجد بيانات للفترة المحددة</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($byCategory) > 0)
                    <tfoot>
                        <tr style="background: #eee; font-weight: bold;">
                            <td>الإجمالي</td>
                            <td style="text-align: center;">{{ $totalCatQty }}</td>
                            <td style="text-align: center; color: var(--primary);">{{ number_format($totalCatRev, 2) }} ريال</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Hourly Distribution --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> توزيع الطلبات حسب الوقت</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered table-striped" style="margin: 0;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th>الساعة</th>
                            <th style="text-align: center;">عدد الطلبات</th>
                            <th>مؤشر الزحام</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $maxOrders = collect($byHour)->max('count') ?: 1; @endphp
                        @forelse($byHour as $hour)
                            @php 
                                $h = sprintf("%02d:00", $hour->hour); 
                                $percent = ($hour->count / $maxOrders) * 100;
                            @endphp
                            <tr>
                                <td style="direction: ltr; text-align: right; font-family: monospace;">{{ $h }}</td>
                                <td style="text-align: center;">{{ $hour->count }}</td>
                                <td style="width: 50%;">
                                    <div style="background: #eee; height: 10px; border-radius: 5px; overflow: hidden;">
                                        <div style="background: var(--primary); height: 100%; width: {{ $percent }}%;"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="text-align: center; color: #888; padding: 20px;">لا توجد بيانات للفترة المحددة</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Top Items --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-star"></i> أعلى الأصناف مبيعاً (Top 20)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" style="margin: 0;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>الصنف</th>
                        <th style="text-align: center;">الكمية المباعة</th>
                        <th style="text-align: center;">الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byItem as $item)
                        <tr>
                            <td><strong>{{ $item->name_ar }}</strong></td>
                            <td style="text-align: center; font-weight: bold; color: #27ae60;">{{ $item->qty }}</td>
                            <td style="text-align: center;">{{ number_format($item->revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align: center; color: #888; padding: 20px;">لا توجد بيانات للفترة المحددة</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
