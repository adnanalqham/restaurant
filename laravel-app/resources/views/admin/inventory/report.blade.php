@extends('layouts.admin')
@section('title', 'تقرير المخزون')

@section('content')
<div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <div class="card" style="background: #27ae60; color: white;">
        <div class="card-body" style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h4 style="margin: 0; opacity: 0.9;">إجمالي قيمة المخزون الحالي</h4>
                <h2 style="margin: 10px 0 0 0;">{{ number_format($totalCost, 2) }} ريال</h2>
            </div>
            <i class="fas fa-boxes fa-3x" style="opacity: 0.5;"></i>
        </div>
    </div>
    
    <div class="card" style="background: #e74c3c; color: white;">
        <div class="card-body" style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h4 style="margin: 0; opacity: 0.9;">نواقص المخزون (تحت الحد الأدنى)</h4>
                <h2 style="margin: 10px 0 0 0;">{{ count($lowStock) }} أصناف</h2>
            </div>
            <i class="fas fa-exclamation-triangle fa-3x" style="opacity: 0.5;"></i>
        </div>
    </div>
</div>

@if(count($lowStock) > 0)
<div class="card mb-20" style="border: 1px solid #e74c3c;">
    <div class="card-header" style="background: #fdf5f5; border-bottom: 1px solid #fad2d2;">
        <h3 style="color: #c0392b;"><i class="fas fa-exclamation-triangle"></i> تنبيه النواقص</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table table-striped" style="margin: 0;">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th style="text-align: center;">الكمية الحالية</th>
                    <th style="text-align: center;">الحد الأدنى</th>
                    <th style="text-align: center;">النقص المقدر</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lowStock as $item)
                    <tr>
                        <td><strong>{{ $item->name }}</strong> <small>({{ $item->unit }})</small></td>
                        <td style="text-align: center; color: #c0392b; font-weight: bold;">{{ $item->current_stock }}</td>
                        <td style="text-align: center;">{{ $item->min_stock }}</td>
                        <td style="text-align: center;">{{ $item->min_stock - $item->current_stock }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-list"></i> تقرير المخزون الشامل</h3>
        <button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> طباعة التقرير</button>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" style="margin: 0;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>#</th>
                        <th>الصنف</th>
                        <th>الوحدة</th>
                        <th style="text-align: center;">الكمية المتوفرة</th>
                        <th style="text-align: center;">الحد الأدنى</th>
                        <th style="text-align: center;">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td><strong>{{ $item->name }}</strong></td>
                            <td>{{ $item->unit }}</td>
                            <td style="text-align: center; font-weight: bold; color: {{ $item->current_stock <= $item->min_stock ? '#e74c3c' : '#27ae60' }}">
                                {{ $item->current_stock }}
                            </td>
                            <td style="text-align: center;">{{ $item->min_stock }}</td>
                            <td style="text-align: center;">
                                @if($item->current_stock <= $item->min_stock)
                                    <span class="badge badge-danger">ناقص</span>
                                @else
                                    <span class="badge badge-success">كافي</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #888;">لا توجد أصناف في المخزن.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        .card, .card * { visibility: visible; }
        .card { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none !important; border: none !important; }
        .btn, form, header, nav, .sidebar { display: none !important; }
    }
</style>
@endsection
