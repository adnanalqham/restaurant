<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<!--[if gte mso 9]><xml>
 <{{'x:ExcelWorkbook'}}><{{'x:ExcelWorksheets'}}><{{'x:ExcelWorksheet'}}>
  <{{'x:Name'}}>إحصائيات الأصناف</{{'x:Name'}}>
  <{{'x:WorksheetOptions'}}><{{'x:DisplayRightToLeft'}} /></{{'x:WorksheetOptions'}}>
 </{{'x:ExcelWorksheet'}}></{{'x:ExcelWorksheets'}}></{{'x:ExcelWorkbook'}}>
</xml><![endif]-->
<style>
  body { font-family: Arial; direction: rtl; }
  table { border-collapse: collapse; width: 100%; }
  td, th { border: 1px solid #ccc; padding: 5px 10px; white-space: nowrap; font-size: 11pt; }
  .title    { background: #1a3c5e; color: #fff; font-size: 14pt; font-weight: bold; text-align: center; }
  .subtitle { background: #2e6da4; color: #fff; font-size: 10pt; text-align: center; }
  .hdr      { background: #2e6da4; color: #fff; font-weight: bold; text-align: center; }
  .alt-row  { background: #f5f9ff; }
  .item-row { background: #ffffff; }
  .num      { text-align: center; }
  .money    { text-align: center; color: #1a3c5e; font-weight: bold; }
  .grand-row{ background: #1a3c5e; color: #fff; font-weight: bold; font-size: 12pt; text-align:center; }
</style>
</head>
<body>
<table>
  <tr><td colspan="4" class="title">{{ $restName }} — إحصائيات الأصناف المباعة</td></tr>
  <tr><td colspan="4" class="subtitle">الفترة: {{ $from === $to ? $from : "$from - $to" }} | إجمالي الحبات: {{ number_format($grandQty) }} | إجمالي الإيرادات: {{ number_format($grandRev, 2) }} ريال</td></tr>
  <tr>
    <th class="hdr">#</th>
    <th class="hdr">اسم الصنف</th>
    <th class="hdr">الأعداد المباعة</th>
    <th class="hdr">المبلغ الإجمالي (ريال)</th>
  </tr>
  @foreach ($itemsData as $idx => $row)
  <tr class="{{ $idx % 2 === 0 ? 'item-row' : 'alt-row' }}">
    <td class="num">{{ $idx + 1 }}</td>
    <td>{{ $row->item_name }}</td>
    <td class="num" style="font-weight:700;color:#1a3c5e">{{ number_format((int)$row->total_qty) }}</td>
    <td class="money">{{ number_format((float)$row->total_revenue, 2) }}</td>
  </tr>
  @endforeach
  <tr class="grand-row">
    <td colspan="2">الإجمالي الكلي — {{ count($itemsData) }} صنف</td>
    <td>{{ number_format($grandQty) }} حبة</td>
    <td>{{ number_format($grandRev, 2) }} ريال</td>
  </tr>
</table>
</body>
</html>
