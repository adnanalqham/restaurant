<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private array $statusLabels = [
        'pending'          => ['color' => 'warning', 'label' => 'معلق'],
        'sent_to_cashier'  => ['color' => 'info',    'label' => 'للكاشير'],
        'confirmed'        => ['color' => 'primary',  'label' => 'مؤكد'],
        'in_progress'      => ['color' => 'warning',  'label' => 'جاري التحضير'],
        'ready'            => ['color' => 'info',     'label' => 'جاهز'],
        'paid'             => ['color' => 'success',  'label' => 'مدفوع'],
        'delivered'        => ['color' => 'success',  'label' => 'تم التسليم'],
        'cancelled'        => ['color' => 'danger',   'label' => 'ملغي'],
    ];

    public function index(Request $request)
    {
        $status = $request->status;
        
        $query = DB::table('orders')
            ->leftJoin('users as w', 'orders.waiter_id', '=', 'w.id')
            ->leftJoin('users as c', 'orders.cashier_id', '=', 'c.id')
            ->select('orders.*', 'w.name as waiter_name', 'c.name as cashier_name')
            ->orderByDesc('orders.created_at');

        // Filters
        if ($status === 'active') {
            $query->whereNotIn('orders.status', ['paid', 'delivered', 'cancelled']);
        } elseif ($status) {
            $query->where('orders.status', $status);
        }

        if ($request->filled('from_date')) $query->whereDate('orders.created_at', '>=', $request->from_date);
        if ($request->filled('to_date'))   $query->whereDate('orders.created_at', '<=', $request->to_date);
        if ($request->filled('date'))      $query->whereDate('orders.created_at', $request->date);
        if ($request->filled('search'))    $query->where('orders.order_number', 'like', '%'.$request->search.'%');

        $orders = $query->paginate(100)->withQueryString();

        // Get items for these orders
        $orderIds = $orders->pluck('id');
        $items = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('order_id');

        foreach($orders as $o) {
            $o->items = $items->get($o->id) ?? collect([]);
        }

        $todayRevenue  = DB::table('orders')->whereDate('created_at', today())->where('status', 'paid')->sum('total');
        $todayCount    = DB::table('orders')->whereDate('created_at', today())->whereNotIn('status',['cancelled'])->count();
        $activeCount   = DB::table('orders')->whereIn('status', ['confirmed','in_progress','ready','sent_to_cashier'])->count();

        return view('admin.orders.index', compact('orders', 'todayRevenue', 'todayCount', 'activeCount'));
    }

    public function deliverAllActive()
    {
        DB::table('orders')
            ->whereNotIn('status', ['paid', 'delivered', 'cancelled'])
            ->update([
                'status'     => 'delivered',
                'updated_at' => now()
            ]);

        return back()->with('success', 'تم تسليم جميع الطلبات النشطة بنجاح');
    }

    public function updateStatus(Request $request, int $order)
    {
        $row = DB::table('orders')->find($order);
        if (!$row) return back()->with('error', 'الطلب غير موجود');

        if ($row->status === 'paid') {
            return back()->with('error', 'لا يمكن تغيير حالة طلب مدفوع');
        }

        $status = $request->status;
        if ($status === 'paid') {
            DB::table('orders')->where('id', $order)->update([
                'status'    => 'paid',
                'paid_at'   => now(),
                'total'     => $request->total ?? $row->total,
            ]);
        } else {
            DB::table('orders')->where('id', $order)->update(['status' => $status]);
        }

        DB::table('sse_events')->insert(['event_type' => 'order_status_changed', 'payload' => json_encode(['order_id' => $order, 'status' => $status]), 'target_roles' => null, 'created_at' => now()]);

        return back()->with('success', 'تم تحديث حالة الطلب');
    }

    public function applyDiscount(Request $request, int $order)
    {
        $row = DB::table('orders')->find($order);
        if (!$row) return back()->with('error', 'الطلب غير موجود');

        if (in_array($row->status, ['paid', 'cancelled', 'delivered'])) {
            return back()->with('error', 'لا يمكن إضافة خصم لطلب تم سداده أو تسليمه');
        }

        $type  = $request->discount_type;   // 'percent' or 'fixed'
        $value = (float) $request->discount_value;
        $sub   = (float) $row->subtotal ?: (float) $row->total;

        $discountAmount = $type === 'percent' ? round($sub * $value / 100, 2) : $value;
        $newTotal       = max(0, round($sub - $discountAmount, 2));

        DB::table('orders')->where('id', $order)->update([
            'discount_type'   => $type,
            'discount_value'  => $value,
            'manual_discount' => $discountAmount,
            'total'           => $newTotal,
        ]);

        return back()->with('success', 'تم تطبيق الخصم بنجاح');
    }

    public function destroy(int $order)
    {
        $row = DB::table('orders')->find($order);
        if (!$row) return back()->with('error', 'الطلب غير موجود');

        if ($row->status === 'paid') {
            return back()->with('error', 'لا يمكن حذف طلب مدفوع');
        }

        DB::table('order_items')->where('order_id', $order)->delete();
        DB::table('orders')->where('id', $order)->delete();
        DB::table('sse_events')->insert(['event_type' => 'order_deleted', 'payload' => json_encode(['order_id' => $order]), 'target_roles' => null, 'created_at' => now()]);

        return redirect()->route('admin.orders.index')->with('success', 'تم حذف الطلب');
    }

    public function details(int $order)
    {
        $o = DB::table('orders')
            ->leftJoin('users as w', 'orders.waiter_id', '=', 'w.id')
            ->leftJoin('users as c', 'orders.cashier_id', '=', 'c.id')
            ->select('orders.*', 'w.name as waiter_name', 'c.name as cashier_name')
            ->where('orders.id', $order)
            ->first();

        if (!$o) return response('<div class="alert alert-danger">الطلب غير موجود</div>');

        $o->items = DB::table('order_items')->where('order_id', $order)->get();

        return view('admin.orders.details', compact('o'));
    }

    public function print(Request $request, int $order)
    {
        $o = DB::table('orders')
            ->leftJoin('users as w', 'orders.waiter_id', '=', 'w.id')
            ->select('orders.*', 'w.name as waiter_name', 'w.name_en as waiter_name_en')
            ->where('orders.id', $order)
            ->first();

        if (!$o) abort(404);

        $items = DB::table('order_items')
            ->where('order_id', $order)
            ->where('status', '!=', 'rejected')
            ->get();

        $copy = $request->query('copy', 'cashier');
        $restName = DB::table('settings')->where('key', 'restaurant_name')->value('value') ?: 'Restaurant';

        $discount = (float)($o->manual_discount ?? 0);
        $subtotal = (float)$o->total + $discount;
        $netTotal = (float)$o->total - (float)($o->refund_amount ?? 0);

        $waiter = $o->waiter_name_en ?: $o->waiter_name ?: '';
        $dt = date('Y-m-d H:i', strtotime($o->created_at));
        $pm = strtoupper($o->payment_method ?? '');

        $receiptLines = $this->buildReceiptLines($restName, (array)$o, $items->toArray(), $discount, $subtotal, $netTotal, $waiter, $dt, $pm);
        $kitchenLines = $this->buildKitchenLines($restName, (array)$o, $items->toArray(), $waiter, $dt);

        return view('admin.orders.print', compact('o', 'receiptLines', 'kitchenLines', 'copy', 'order'));
    }

    private function buildReceiptLines($restName, $order, $items, $discount, $subtotal, $netTotal, $waiter, $dt, $pm)
    {
        $W = 32;
        $SEP = str_repeat('=', $W);
        $DIV = str_repeat('-', $W);
        $lines = [];

        $lines[] = ['text' => $this->cLine($restName, $W), 'bold' => true, 'big' => true];
        $lines[] = ['text' => $this->cLine('SALES RECEIPT', $W), 'bold' => true];
        $lines[] = ['text' => $SEP];
        $lines[] = ['text' => $this->rLine('Order No.', '#' . $order['order_number'], $W)];
        $lines[] = ['text' => $this->rLine('Table    ', $order['table_number'] ?: 'Takeaway', $W)];
        if ($waiter) $lines[] = ['text' => $this->rLine('Waiter   ', $waiter, $W)];
        $lines[] = ['text' => $this->rLine('Date     ', $dt, $W)];
        if (!empty($order['notes'])) {
            $n = trim(preg_replace('/[^\x20-\x7E]/', '', $order['notes']));
            if ($n) $lines[] = ['text' => $this->rLine('Note     ', substr($n, 0, 14), $W)];
        }
        $lines[] = ['text' => $DIV];

        foreach ($items as $item) {
            $item = (array)$item;
            $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['item_name_en'] ?? ''));
            if (!$name) $name = 'Item';
            if (strlen($name) > $W) $name = substr($name, 0, $W - 1) . '.';

            $total = number_format((float)$item['subtotal'], 2);
            $price = number_format((float)$item['unit_price'], 2);
            $qty = (int)$item['quantity'];

            $namePad = max(1, $W - strlen($name) - strlen($total));
            $lines[] = ['text' => $name . str_repeat(' ', $namePad) . $total];

            $qStr = 'x' . $qty . ' @ ' . $price;
            $lines[] = ['text' => str_repeat(' ', max(0, $W - strlen($qStr))) . $qStr];

            if (!empty($item['notes'])) {
                $note = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
                if ($note) $lines[] = ['text' => '  * ' . substr($note, 0, $W - 5)];
            }
        }

        $lines[] = ['text' => $DIV];
        if ($discount > 0) {
            $lines[] = ['text' => $this->rLine('Subtotal', number_format($subtotal, 2), $W)];
            $lines[] = ['text' => $this->rLine('Discount', '-' . number_format($discount, 2), $W)];
            $lines[] = ['text' => $DIV];
        }
        if ($pm) $lines[] = ['text' => $this->rLine('Payment ', $pm, $W)];
        $lines[] = ['text' => $SEP];
        $lines[] = ['text' => $this->rLine('TOTAL', number_format($netTotal, 2) . ' YER', $W), 'bold' => true];
        $lines[] = ['text' => $SEP];
        $lines[] = ['text' => $this->cLine('Thank you for visiting!', $W)];

        return $lines;
    }

    private function buildKitchenLines($restName, $order, $items, $waiter, $dt)
    {
        $W = 32;
        $SEP = str_repeat('=', $W);
        $lines = [];

        $lines[] = ['text' => $this->cLine($restName, $W), 'bold' => true];
        $lines[] = ['text' => $this->cLine('KITCHEN / WAITER COPY', $W), 'bold' => true];
        $lines[] = ['text' => $SEP];
        $lines[] = ['text' => $this->rLine('Order No.', '#' . $order['order_number'], $W)];
        $lines[] = ['text' => $this->rLine('Table    ', $order['table_number'] ?: 'Takeaway', $W)];
        if ($waiter) $lines[] = ['text' => $this->rLine('Waiter   ', $waiter, $W)];
        $lines[] = ['text' => $this->rLine('Time     ', $dt, $W)];
        $lines[] = ['text' => $SEP];

        foreach ($items as $item) {
            $item = (array)$item;
            $name = trim(preg_replace('/[^\x20-\x7E]/', '', $item['item_name_en'] ?? ''));
            if (!$name) $name = 'Item';
            $qty = (int)$item['quantity'];

            $qStr = 'x' . $qty;
            $pad = max(1, $W - strlen($qStr) - strlen($name));
            $lines[] = ['text' => $qStr . str_repeat(' ', $pad) . $name, 'bold' => true];

            if (!empty($item['notes'])) {
                $note = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
                if ($note) $lines[] = ['text' => '  ** ' . substr($note, 0, $W - 7) . ' **'];
            }
            $lines[] = ['text' => str_repeat('-', $W)];
        }

        $lines[] = ['text' => $SEP];
        $lines[] = ['text' => $this->cLine('** HANDLE WITH CARE **', $W)];

        return $lines;
    }

    private function rLine($label, $value, $w) {
        $pad = max(1, $w - strlen($label) - strlen($value));
        return $label . str_repeat(' ', $pad) . $value;
    }

    private function cLine($text, $w) {
        $text = substr($text, 0, $w);
        $pad = max(0, (int)(($w - strlen($text)) / 2));
        return str_repeat(' ', $pad) . $text;
    }
}
