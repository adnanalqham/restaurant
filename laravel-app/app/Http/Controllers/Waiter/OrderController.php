<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function create()
    {
        $categories = DB::table('categories')->where('is_active', 1)->orderBy('sort_order')->get();
        $items = DB::table('items')
            ->where('is_available', 1)
            ->orderBy('sort_order')
            ->get();
        
        // Fetch Offers (Meal Packages)
        $offers = DB::table('offers')
            ->where('is_active', 1)
            ->get()
            ->map(function($offer) {
                return (object)[
                    'id' => 'offer_' . $offer->id,
                    'is_combo' => true,
                    'category_id' => 'offers',
                    'name_ar' => $offer->name_ar,
                    'name_en' => $offer->name_en ?? 'Offer',
                    'price' => $offer->price,
                    'image' => $offer->image ?? null,
                    'description_ar' => $offer->description_ar ?? 'عرض خاص',
                    'has_sizes' => 0,
                    'has_addons' => 0
                ];
            });

        // Merge items and offers
        $allItems = $items->concat($offers);
        
        $wallets = DB::table('wallets')->where('is_active', 1)->orderBy('sort_order')->get();
        
        $cashiers = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.name', 'cashier')
            ->where('users.is_active', 1)
            ->select('users.id', 'users.name')
            ->get();

        return view('waiter.orders.create', [
            'categories' => $categories,
            'items' => $allItems,
            'wallets' => $wallets,
            'cashiers' => $cashiers
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_number' => 'required',
            'items'        => 'required|array|min:1',
            'cashier_id'   => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            // Order Number Generation (Simple)
            $lastOrder = DB::table('orders')->orderByDesc('id')->first();
            $orderNumber = $lastOrder ? ($lastOrder->id + 1000) : 1001;

            $orderId = DB::table('orders')->insertGetId([
                'order_number'    => $orderNumber,
                'table_number'    => $request->table_number,
                'waiter_id'       => auth()->id(),
                'cashier_id'      => $request->cashier_id,
                'status'          => 'pending',
                'payment_method'  => $request->payment_method ?? 'cash',
                'wallet_id'       => $request->wallet_id,
                'total'           => $request->total ?? 0,
                'notes'           => $request->notes,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $total = 0;
            foreach ($request->items as $item) {
                $isOffer = strpos($item['id'], 'offer_') === 0;
                $numericId = $isOffer ? str_replace('offer_', '', $item['id']) : $item['id'];
                $subtotal = (float)$item['price'] * $item['quantity'];
                $total += $subtotal;

                DB::table('order_items')->insert([
                    'order_id'     => $orderId,
                    'item_id'      => $isOffer ? null : $numericId,
                    'combo_id'     => $isOffer ? $numericId : null,
                    'item_name_ar' => $item['name_ar'],
                    'item_name_en' => $item['name_en'] ?? null,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => (float)$item['price'],
                    'subtotal'     => $subtotal,
                    'status'       => 'pending',
                    'notes'        => $item['notes'] ?? null,
                    'size_name'    => $item['size_name'] ?? null,
                    'addons'       => isset($item['addons']) ? json_encode($item['addons']) : null,
                    'created_at'   => now(),
                ]);
            }

            DB::table('orders')->where('id', $orderId)->update(['subtotal' => $total, 'total' => $total]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم إرسال الطلب بنجاح', 'order_id' => $orderId]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'فشل إرسال الطلب: ' . $e->getMessage()], 500);
        }
    }

    public function index()
    {
        $orders = DB::table('orders')
            ->where('waiter_id', auth()->id())
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach($orders as $order) {
            $order->items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->get();
        }

        return view('waiter.orders.index', compact('orders'));
    }
}
