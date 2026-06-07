<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>{{ $copy === 'kitchen' ? 'تذكرة مطبخ' : 'فاتورة' }} #{{ $order->order_number }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      background: #fff;
      direction: ltr; /* Receipts usually print better LTR with manual Arabic spacing, but keeping it simple */
    }

    .receipt-block {
      width: 280px;
      margin: 0 auto;
      padding: 6px 4px;
    }

    .cut-line {
      margin: 8px 0;
      border: none;
      border-top: 1px dashed #555;
      position: relative;
      width: 280px;
      margin-left: auto;
      margin-right: auto;
    }
    .cut-line::after {
      content: '✂';
      position: absolute;
      top: -9px;
      left: 50%;
      transform: translateX(-50%);
      background: #fff;
      padding: 0 4px;
      font-size: 13px;
      color: #555;
    }

    pre {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      white-space: pre;
      line-height: 1.45;
      color: #000;
    }
    pre.bold { font-weight: bold; }
    pre.big  { font-size: 14px; font-weight: bold; }

    .kitchen-bar {
      background: #ea580c;
      color: #fff;
      text-align: center;
      font-weight: bold;
      font-size: 13px;
      letter-spacing: 2px;
      padding: 8px;
      margin-bottom: 8px;
    }

    .btn-bar {
      display: flex;
      justify-content: center;
      gap: 8px;
      padding: 14px;
      direction: rtl;
    }
    .btn {
      padding: 8px 24px;
      font-size: 13px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: bold;
      font-family: inherit;
    }
    .btn-p { background: #1d4ed8; color: #fff; }
    .btn-c { background: #6b7280; color: #fff; }

    @media print {
      @page {
        margin: 4mm;
      }
      body { background: none; }
      .receipt-block { width: 100%; padding: 0; }
      .kitchen-bar, .btn-bar { display: none; }
      .cut-line { display: none; }
      .page-cut {
        page-break-before: always;
        break-before: page;
        padding-top: 2mm;
      }
    }
  </style>
</head>
<body>

@if ($copy === 'kitchen')

  <!-- Kitchen bar (screen only) -->
  <div class="kitchen-bar">🍳 KITCHEN TICKET</div>

  <!-- Kitchen ticket -->
  <div class="receipt-block" style="page-break-after: always; break-after: page;">
    @foreach ($kitchenLines as $l)
      <pre class="{{ !empty($l['big']) ? 'big' : (!empty($l['bold']) ? 'bold' : '') }}">{{ htmlspecialchars($l['text']) }}</pre>
    @endforeach
  </div>

  <div class="btn-bar no-print-cut">
    <button class="btn btn-p" onclick="window.print()">🖨️ طباعة المطبخ</button>
    <button class="btn btn-c" onclick="window.close()">✕ إغلاق</button>
  </div>

  <script>
    document.querySelector('.btn-p').focus();
  </script>

@else

  <!-- Copy 1: Customer Receipt -->
  <div class="receipt-block">
    @foreach ($receiptLines as $l)
      <pre class="{{ !empty($l['big']) ? 'big' : (!empty($l['bold']) ? 'bold' : '') }}">{{ htmlspecialchars($l['text']) }}</pre>
    @endforeach
  </div>

  <pre style="line-height:1.45"> </pre>
  <pre style="line-height:1.45"> </pre>
  <pre style="line-height:1.45"> </pre>
  <!-- Visual cut line (screen only) -->
  <hr class="cut-line">

  <!-- Copy 2: Waiter copy -->
  <div class="receipt-block page-cut">
    @foreach ($kitchenLines as $l)
      <pre class="{{ !empty($l['big']) ? 'big' : (!empty($l['bold']) ? 'bold' : '') }}">{{ htmlspecialchars($l['text']) }}</pre>
    @endforeach
  </div>

  <div class="btn-bar no-print-cut">
    <button class="btn btn-p" onclick="window.print()">🖨️ طباعة الفاتورة</button>
    <button class="btn btn-c" onclick="window.close()">✕ إغلاق</button>
  </div>

  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.print();
      }, 700);
    });
  </script>

@endif

</body>
</html>
