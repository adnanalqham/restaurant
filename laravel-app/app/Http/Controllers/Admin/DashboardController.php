<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Today's stats
        $today = now()->toDateString();

        $todayOrders  = DB::table('orders')->whereDate('created_at', $today)->whereNotIn('status', ['cancelled'])->count();
        $todayRevenue = DB::table('orders')->whereDate('created_at', $today)->whereNotIn('status', ['cancelled'])->sum('total');
        $pendingOrders = DB::table('orders')->whereIn('status', ['sent_to_cashier', 'confirmed', 'in_progress'])->count();
        $totalItems   = DB::table('items')->where('is_available', 1)->count();

        // Recent orders
        $recentOrders = DB::table('orders')
            ->join('users as w', 'orders.waiter_id', '=', 'w.id')
            ->select('orders.*', 'w.name as waiter_name')
            ->orderByDesc('orders.created_at')
            ->limit(10)
            ->get();

        // Low stock items
        $lowStock = DB::table('inv_items')
            ->whereRaw('current_stock <= min_stock')
            ->orderBy('current_stock')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'todayOrders', 'todayRevenue', 'pendingOrders', 'totalItems',
            'recentOrders', 'lowStock'
        ));
    }
}
