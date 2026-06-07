<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<!--[if gte mso 9]><xml>
 <{{'x:ExcelWorkbook'}}><{{'x:ExcelWorksheets'}}><{{'x:ExcelWorksheet'}}>
  <{{'x:Name'}}>تقرير المبيعات</{{'x:Name'}}>
  <{{'x:WorksheetOptions'}}><{{'x:DisplayRightToLeft'}} /></{{'x:WorksheetOptions'}}>
 </{{'x:ExcelWorksheet'}}></{{'x:ExcelWorksheets'}}></{{'x:ExcelWorkbook'}}>
</xml><![endif]-->
<style>
  body { font-family: Arial; direction: rtl; }
  table { border-collapse: collapse; width: 100%; }
  td, th { border: 1px solid #ccc; padding: 5px 8px; white-space: nowrap; font-size: 11pt; }
  .title    { background: #1a3c5e; color: #fff; font-size: 14pt; font-weight: bold; text-align: center; }
  .subtitle { background: #2e6da4; color: #fff; font-size: 10pt; text-align: center; }
  .hdr      { background: #2e6da4; color: #fff; font-weight: bold; text-align: center; }
  .order-hdr{ background: #f0f4fa; font-weight: bold; color: #1a3c5e; }
  .item-row { background: #ffffff; }
  .item-row td:first-child { background: #f8fbff; }
  .alt-row  { background: #f5f5f5; }
  .total-row{ background: #e8f4e8; font-weight: bold; color: #1d6f42; }
  .grand-row{ background: #1a3c5e; color: #fff; font-weight: bold; font-size: 12pt; }
  .sep      { height: 8px; background: #e0e0e0; }
  .num      { text-align: center; }
  .money    { text-align: center; color: #1a3c5e; font-weight: bold; }
  .discount { text-align: center; color: #c0392b; }
  .net      { text-align: center; color: #1d6f42; font-weight: bold; }
  .muted    { color: #999; text-align: center; }
  .badge-cash   { background:#27ae60; color:#fff; padding:1px 6px; border-radius:4px; }
  .badge-wallet { background:#2980b9; color:#fff; padding:1px 6px; border-radius:4px; }
</style>
</head>
<body>
<table>
@if ($mode === 'summary')
  <!-- ═══ SUMMARY MODE ═══ -->
  <tr><td colspan="9" class="title">{{ $restName }} — كشف مبيعات</td></tr>
  <tr><td colspan="9" class="subtitle">التاريخ: {{ $from === $to ? $from : "$from - $to" }}  |  عدد الطلبات: {{ count($orders) }}  |  الإيرادات: {{ number_format($totalNet, 2) }} ريال</td></tr>
  <tr>
    <th class="hdr">#</th>
    <th class="hdr">رقم الطلب</th>
    <th class="hdr">طريقة الدفع</th>
    <th class="hdr">الحالة</th>
    <th class="hdr">الوقت</th>
    <th class="hdr">الويتر</th>
    <th class="hdr">قبل الخصم</th>
    <th class="hdr">الخصم</th>
    <th class="hdr">الصافي</th>
  </tr>
  @php $i = 1; @endphp
  @foreach ($orders as $o)
  @php
    $beforeDisc = (float)$o->total + (float)($o->manual_discount ?? 0);
    $net = (float)$o->total - (float)($o->refund_amount ?? 0);
    $rowClass = ($i % 2 === 0) ? 'alt-row' : 'item-row';
  @endphp
  <tr class="{{ $rowClass }}">
    <td class="num">{{ $i++ }}</td>
    <td class="num">{{ $o->order_number }}</td>
    <td class="num">
      @if ($o->payment_method === 'cash')
        كاش
      @elseif ($o->payment_method === 'wallet')
        محفظة
      @else
        -
      @endif
    </td>
    <td class="num">{{ $o->status }}</td>
    <td class="num">{{ date('h:i A', strtotime($o->created_at)) }}</td>
    <td>{{ $o->waiter_name ?? '-' }}</td>
    <td class="money">{{ number_format($beforeDisc, 2) }}</td>
    <td class="discount">{{ number_format((float)($o->manual_discount ?? 0), 2) }}</td>
    <td class="net">{{ number_format($net, 2) }}</td>
  </tr>
  @endforeach
  <tr class="grand-row">
    <td colspan="6" style="text-align:center">الإجمالي الكلي — {{ count($orders) }} طلب</td>
    <td class="num">{{ number_format($totalBeforeDisc, 2) }}</td>
    <td class="num">{{ number_format($totalDiscount, 2) }}</td>
    <td class="num">{{ number_format($totalNet, 2) }} ريال</td>
  </tr>

@else
  <!-- ═══ DETAILED MODE ═══ -->
  <tr><td colspan="8" class="title">{{ $restName }} — كشف مبيعات تفصيلي</td></tr>
  <tr><td colspan="8" class="subtitle">التاريخ: {{ $from === $to ? $from : "$from - $to" }}  |  عدد الطلبات: {{ count($orders) }}  |  الإيرادات: {{ number_format($totalNet, 2) }} ريال</td></tr>
  <tr>
    <th class="hdr">رقم الطلب</th>
    <th class="hdr">الصنف</th>
    <th class="hdr">الكمية</th>
    <th class="hdr">سعر الوحدة</th>
    <th class="hdr">إجمالي الصنف</th>
    <th class="hdr">الويتر</th>
    <th class="hdr">الوقت</th>
    <th class="hdr">طريقة الدفع</th>
  </tr>
  @foreach ($orders as $idx => $o)
  @php
    $beforeDisc = (float)$o->total + (float)($o->manual_discount ?? 0);
    $net = (float)$o->total - (float)($o->refund_amount ?? 0);
    $items = $o->items ?? [];
    $itemCount = count($items);
    $mergeRows = max($itemCount, 1);
  @endphp
  @if ($idx > 0)
  <tr><td colspan="8" class="sep"></td></tr>
  @endif

  @if ($itemCount > 0)
    @foreach ($items as $iIdx => $item)
    <tr class="{{ $iIdx % 2 === 0 ? 'item-row' : 'alt-row' }}">
      @if ($iIdx === 0)
        <td class="num order-hdr" rowspan="{{ $mergeRows + 1 }}">{{ $o->order_number }}</td>
      @endif
      <td>{{ $item->item_name_ar }}</td>
      <td class="num">{{ (int)$item->quantity }}</td>
      <td class="money">{{ number_format((float)$item->unit_price, 2) }}</td>
      <td class="money">{{ number_format((float)($item->unit_price * $item->quantity), 2) }}</td>
      @if ($iIdx === 0)
        <td rowspan="{{ $mergeRows + 1 }}">{{ $o->waiter_name ?? '-' }}</td>
        <td class="num" rowspan="{{ $mergeRows + 1 }}">{{ date('h:i A', strtotime($o->created_at)) }}</td>
        <td class="num" rowspan="{{ $mergeRows + 1 }}">
          @if ($o->payment_method === 'cash') كاش @elseif ($o->payment_method === 'wallet') محفظة @else - @endif
        </td>
      @endif
    </tr>
    @endforeach
  @else
    <tr class="item-row">
      <td class="num order-hdr" rowspan="2">{{ $o->order_number }}</td>
      <td colspan="4" class="muted">لا توجد أصناف</td>
      <td>{{ $o->waiter_name ?? '-' }}</td>
      <td class="num">{{ date('h:i A', strtotime($o->created_at)) }}</td>
      <td class="num">
        @if ($o->payment_method === 'cash') كاش @else - @endif
      </td>
    </tr>
  @endif
  <tr class="total-row">
    <td colspan="3" style="text-align:right;padding-right:10px">
      الإجمالي — {{ $itemCount }} صنف
      @if ((float)($o->manual_discount ?? 0) > 0)
        | خصم: {{ number_format((float)$o->manual_discount, 2) }} ريال
      @endif
    </td>
    <td class="net">{{ number_format($net, 2) }} ريال</td>
    <td colspan="3"></td>
  </tr>
  @endforeach

  <tr><td colspan="8" class="sep"></td></tr>
  <tr class="grand-row">
    <td colspan="3" style="text-align:center;font-size:13pt">الإجمالي الكلي — {{ count($orders) }} طلب</td>
    <td colspan="2" style="text-align:center;font-size:13pt">{{ number_format($totalNet, 2) }} ريال</td>
    <td colspan="3" style="text-align:center">إجمالي الخصومات: {{ number_format($totalDiscount, 2) }} ريال</td>
  </tr>
@endif
</table>
</body>
</html>
