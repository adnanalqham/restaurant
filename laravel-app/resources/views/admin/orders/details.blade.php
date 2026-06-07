<div class="order-details-modal">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px">
        <h4 style="margin:0; color:var(--primary)">معلومات الطلب</h4>
        <div style="display:flex; gap:10px">
            <button class="btn btn-secondary btn-sm" onclick="printOrder('receipt', {{ $o->id }})"><i class="fas fa-bolt"></i> طباعة فورية</button>
            <button class="btn btn-warning btn-sm" onclick="printOrder('kitchen', {{ $o->id }})" style="color:#fff"><i class="fas fa-fire"></i> طباعة للمطبخ</button>
            <a href="{{ route('admin.orders.print', $o->id) }}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> طباعة المتصفح</a>
        </div>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px; font-size:0.95rem; background:#f9f9f9; padding:15px; border-radius:10px; border:1px solid #eee">
        <div><strong style="color:#666">رقم الطلب:</strong> #{{ $o->order_number }}</div>
        <div><strong style="color:#666">الحالة:</strong> <span class="badge badge-info">{{ $o->status }}</span></div>
        <div><strong style="color:#666">التاريخ:</strong> {{ \Carbon\Carbon::parse($o->created_at)->format('Y-m-d H:i') }}</div>
        <div><strong style="color:#666">الطاولة:</strong> {{ $o->table_number ?: '---' }}</div>
        <div><strong style="color:#666">الويتر:</strong> {{ $o->waiter_name ?? '---' }}</div>
        <div><strong style="color:#666">الكاشير:</strong> {{ $o->cashier_name ?? '---' }}</div>
        <div><strong style="color:#666">الدفع:</strong> {{ $o->payment_method ?? '---' }}</div>
        @if($o->payment_reference)
            <div><strong style="color:#666">المرجع:</strong> {{ $o->payment_reference }}</div>
        @endif
    </div>

    @if($o->notes)
    <div style="background:#fff5e6; padding:10px; border-radius:8px; border-right:4px solid #f39c12; margin-bottom:20px; font-size:0.9rem">
        <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong> {{ $o->notes }}
    </div>
    @endif

    <div class="table-responsive">
        <table class="table" style="width:100%; border-collapse:collapse">
            <thead>
                <tr style="background:#f1f1f1; border-bottom:2px solid #ddd">
                    <th style="text-align:right; padding:12px">الصنف</th>
                    <th style="text-align:center; padding:12px">الكمية</th>
                    <th style="text-align:center; padding:12px">السعر</th>
                    <th style="text-align:center; padding:12px">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($o->items as $i)
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:12px">{{ $i->item_name_ar }}</td>
                    <td style="text-align:center; padding:12px">x{{ (int)$i->quantity }}</td>
                    <td style="text-align:center; padding:12px">{{ number_format($i->unit_price, 2) }}</td>
                    <td style="text-align:center; padding:12px; font-weight:600">{{ number_format($i->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="background:#f9f9f9">
                <tr>
                    <td colspan="3" style="text-align:left; padding:10px"><strong>المجموع الفرعي:</strong></td>
                    <td style="text-align:center; padding:10px"><strong>{{ number_format($o->subtotal ?: ($o->total + $o->manual_discount), 2) }}</strong></td>
                </tr>
                @if($o->manual_discount > 0)
                <tr style="color:#e74c3c">
                    <td colspan="3" style="text-align:left; padding:10px"><strong>الخصم:</strong></td>
                    <td style="text-align:center; padding:10px"><strong>-{{ number_format($o->manual_discount, 2) }}</strong></td>
                </tr>
                @endif
                <tr style="font-size:1.2rem; background:#eee">
                    <td colspan="3" style="text-align:left; padding:10px"><strong>الإجمالي النهائي:</strong></td>
                    <td style="text-align:center; padding:10px; color:var(--primary)"><strong>{{ number_format($o->total, 2) }} ريال</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
