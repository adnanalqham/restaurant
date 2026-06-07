<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    public function stats()
    {
        $from = request('from', today()->subDays(6)->toDateString());
        $to   = request('to',   today()->toDateString());

        $todayRevenue  = DB::table('orders')->whereDate('created_at', today())->where('status', 'paid')->sum('total');
        $periodRevenue = DB::table('orders')->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])->where('status', 'paid')->sum('total');
        $activeOrders  = DB::table('orders')->whereIn('status', ['pending','sent_to_cashier','confirmed','in_progress','ready'])->count();
        $itemsCount    = DB::table('items')->where('is_available', 1)->count();

        $recentOrders = DB::table('orders')
            ->join('users as w', 'orders.waiter_id', '=', 'w.id')
            ->select('orders.id','orders.order_number','orders.table_number','orders.total','orders.status','w.name as waiter_name')
            ->orderByDesc('orders.created_at')
            ->limit(6)
            ->get();

        $lowStock = DB::table('inv_items')
            ->whereRaw('current_stock <= min_stock')
            ->where('min_stock', '>', 0)
            ->select('name', 'current_stock', 'min_stock', 'unit')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'today_revenue'  => $todayRevenue,
                'period_revenue' => $periodRevenue,
                'active_orders'  => $activeOrders,
                'items_count'    => $itemsCount,
                'recent_orders'  => $recentOrders,
                'low_stock'      => $lowStock,
            ]
        ]);
    }

    public function charts()
    {
        $from = request('from', today()->subDays(6)->toDateString());
        $to   = request('to',   today()->toDateString());

        // Revenue trend
        $trend = DB::table('orders')
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->where('status', 'paid')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Categories
        $categories = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('categories', 'order_items.category_id', '=', 'categories.id')
            ->selectRaw('COALESCE(categories.name_ar, "أخرى") as cat_name, SUM(order_items.unit_price * order_items.quantity) as total_revenue')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from, $to])
            ->where('orders.status', 'paid')
            ->groupBy('order_items.category_id', 'categories.name_ar')
            ->orderByDesc('total_revenue')
            ->get();

        // Waiters performance
        $waiters = DB::table('orders')
            ->join('users as w', 'orders.waiter_id', '=', 'w.id')
            ->selectRaw('w.name as waiter_name, COUNT(*) as orders_count, SUM(orders.total) as total_revenue')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from, $to])
            ->where('orders.status', 'paid')
            ->groupBy('orders.waiter_id', 'w.name')
            ->orderByDesc('orders_count')
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => compact('trend', 'categories', 'waiters')
        ]);
    }
}
