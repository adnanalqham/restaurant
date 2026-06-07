<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesStatsController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ?? today()->subDays(30)->toDateString();
        $to   = $request->to   ?? today()->toDateString();

        // Sales by category
        $byCategory = DB::table('order_items')
            ->join('orders','order_items.order_id','=','orders.id')
            ->join('categories','order_items.category_id','=','categories.id')
            ->selectRaw('categories.name_ar, SUM(order_items.quantity) as qty, SUM(order_items.unit_price * order_items.quantity) as revenue')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from, $to])
            ->where('orders.status','paid')
            ->groupBy('categories.id','categories.name_ar')
            ->orderByDesc('revenue')
            ->get();

        // Sales by item
        $byItem = DB::table('order_items')
            ->join('orders','order_items.order_id','=','orders.id')
            ->selectRaw('order_items.item_name_ar as name_ar, SUM(order_items.quantity) as qty, SUM(order_items.unit_price * order_items.quantity) as revenue')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from, $to])
            ->where('orders.status','paid')
            ->groupBy('order_items.item_name_ar')
            ->orderByDesc('qty')
            ->limit(20)
            ->get();

        // Hourly distribution
        $byHour = DB::table('orders')
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->where('status','paid')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return view('admin.sales_stats.index', compact('byCategory','byItem','byHour','from','to'));
    }
}
