<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderApiController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('orders')
            ->join('users as w', 'orders.waiter_id', '=', 'w.id')
            ->select('orders.*', 'w.name as waiter_name')
            ->orderByDesc('orders.created_at');

        if ($request->status === 'active') {
            $query->whereNotIn('orders.status', ['paid', 'delivered', 'cancelled']);
        } elseif ($request->filled('status') && $request->status !== 'all') {
            $query->where('orders.status', $request->status);
        }
        
        if ($request->filled('date'))   $query->whereDate('orders.created_at', $request->date);

        if ($user->getRoleName() === 'waiter') {
            $query->where('orders.waiter_id', $user->id);
        }

        $orders = $query->limit(100)->get();

        // Attach items
        $orderIds = $orders->pluck('id')->toArray();
        $items = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('order_id');

        $orders = $orders->map(function ($o) use ($items) {
            $o->items = $items[$o->id] ?? collect();
            return $o;
        });

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_number' => 'required',
            'items'        => 'required|array|min:1',
        ]);

        $prefix = date('Y');
        $last = DB::table('orders')->where('order_number', 'REGEXP', '^' . $prefix . '[0-9]+$')->orderByDesc('id')->value('order_number');
        $seq  = $last ? ((int) substr($last, 4) + 1) : 1;
        $orderNumber = $prefix . str_pad($seq, 2, '0', STR_PAD_LEFT);

        $subtotal = collect($request->items)->sum(fn($i) => ($i['price'] ?? 0) * ($i['qty'] ?? 1));

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => $orderNumber,
            'table_number' => $request->table_number,
            'waiter_id'    => auth()->id(),
            'notes'        => $request->notes,
            'status'       => 'pending',
            'subtotal'     => $subtotal,
            'total'        => $subtotal,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        foreach ($request->items as $item) {
            DB::table('order_items')->insert([
                'order_id'     => $orderId,
                'item_id'      => $item['id'],
                'category_id'  => $item['category_id'] ?? null,
                'item_name_ar' => $item['name_ar'],
                'size_name'    => $item['size_name'] ?? null,
                'unit_price'   => $item['price'],
                'quantity'     => $item['quantity'] ?? $item['qty'],
                'subtotal'     => ($item['price']) * ($item['quantity'] ?? $item['qty']),
                'notes'        => $item['notes'] ?? null,
                'status'       => 'pending',
            ]);
        }

        DB::table('sse_events')->insert(['event_type' => 'new_order', 'payload' => json_encode(['order_id' => $orderId, 'order_number' => $orderNumber, 'table' => $request->table_number]), 'target_roles' => null, 'created_at' => now()]);

        return response()->json(['success' => true, 'data' => ['id' => $orderId, 'order_number' => $orderNumber], 'message' => 'تم إنشاء الطلب بنجاح']);
    }

    public function updateStatus(Request $request)
    {
        $id     = $request->order_id;
        $status = $request->status;
        $order  = DB::table('orders')->find($id);

        if (!$order) return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);
        if ($order->status === 'paid') return response()->json(['success' => false, 'message' => 'لا يمكن تعديل طلب مدفوع'], 400);
        if ($order->status === 'cancelled') return response()->json(['success' => false, 'message' => 'لا يمكن تعديل طلب ملغي'], 400);

        $user = auth()->user();
        if ($status === 'cancelled' && $order->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'لا يمكن إلغاء طلب بعد الدفع ✅'], 400);
        }
        if ($status === 'cancelled' && $user->getRoleName() === 'cashier' && in_array($order->status, ['confirmed','in_progress','ready'])) {
            return response()->json(['success' => false, 'message' => 'تواصل مع المدير لإلغاء الطلب'], 403);
        }

        $upd = ['status' => $status, 'updated_at' => now()];
        if ($status === 'paid') { $upd['paid_at'] = now(); $upd['total'] = $request->total ?? $order->total; }

        DB::table('orders')->where('id', $id)->update($upd);
        DB::table('sse_events')->insert(['event_type' => 'order_status_changed', 'payload' => json_encode(['order_id' => $id, 'status' => $status, 'order_number' => $order->order_number]), 'target_roles' => null, 'created_at' => now()]);

        return response()->json(['success' => true, 'message' => 'تم تحديث الحالة']);
    }

    public function applyDiscount(Request $request)
    {
        $id    = $request->order_id;
        $order = DB::table('orders')->find($id);
        if (!$order) return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);
        if (in_array($order->status, ['paid', 'cancelled', 'delivered'])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إضافة خصم لطلب تم سداده'], 400);
        }

        $type   = $request->discount_type;
        $value  = (float) $request->discount_value;
        $sub    = (float) $order->subtotal ?: (float) $order->total;
        $amount = $type === 'percent' ? round($sub * $value / 100, 2) : $value;
        $total  = max(0, round($sub - $amount, 2));

        DB::table('orders')->where('id', $id)->update(['discount_type' => $type, 'discount_value' => $value, 'discount_amount' => $amount, 'total' => $total]);

        return response()->json(['success' => true, 'data' => ['new_total' => $total], 'message' => 'تم تطبيق الخصم']);
    }

    public function deleteItem(Request $request)
    {
        $itemId  = $request->item_id;
        $orderItem = DB::table('order_items')->find($itemId);
        if (!$orderItem) return response()->json(['success' => false, 'message' => 'الصنف غير موجود'], 404);

        $order = DB::table('orders')->find($orderItem->order_id);
        if ($order && $order->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف صنف من طلب مدفوع'], 400);
        }

        DB::table('order_items')->where('id', $itemId)->delete();

        // Recalculate total
        $newSubtotal = DB::table('order_items')->where('order_id', $orderItem->order_id)->sum('subtotal');
        DB::table('orders')->where('id', $orderItem->order_id)->update(['subtotal' => $newSubtotal, 'total' => $newSubtotal]);

        return response()->json(['success' => true, 'message' => 'تم حذف الصنف']);
    }

    public function show(int $id)
    {
        $order = DB::table('orders')
            ->join('users as w', 'orders.waiter_id', '=', 'w.id')
            ->select('orders.*', 'w.name as waiter_name')
            ->where('orders.id', $id)
            ->first();

        if (!$order) return response()->json(['success' => false, 'message' => 'الطلب غير موجود'], 404);

        $order->items = DB::table('order_items')->where('order_id', $id)->get();

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function updateItemStatus(Request $request)
    {
        $itemId = $request->id;
        $status = $request->status;

        DB::table('order_items')->where('id', $itemId)->update(['status' => $status]);

        return response()->json(['success' => true, 'message' => 'تم تحديث حالة الصنف']);
    }

    public function prepareAll(Request $request)
    {
        $orderId = $request->order_id;
        $status  = $request->status ?? 'ready';

        DB::table('order_items')->where('order_id', $orderId)->update(['status' => $status]);

        return response()->json(['success' => true, 'message' => 'تم تحديث جميع الأصناف']);
    }

    public function destroy(int $id)
    {
        $order = DB::table('orders')->find($id);
        if (!$order) return response()->json(['success' => false, 'message' => 'غير موجود'], 404);
        if ($order->status === 'paid') return response()->json(['success' => false, 'message' => 'لا يمكن حذف طلب مدفوع'], 403);

        DB::table('order_items')->where('order_id', $id)->delete();
        DB::table('orders')->where('id', $id)->delete();
        DB::table('sse_events')->insert(['event_type' => 'order_deleted', 'payload' => json_encode(['order_id' => $id]), 'target_roles' => null, 'created_at' => now()]);

        return response()->json(['success' => true, 'message' => 'تم حذف الطلب']);
    }
}
