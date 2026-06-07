<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type', 'range');
        $date = $request->get('date', today()->toDateString());
        $from = $request->get('from', today()->subMonths(3)->toDateString());
        $to   = $request->get('to', today()->toDateString());

        if ($type === 'daily') {
            $queryFrom = $date;
            $queryTo   = $date;
        } else {
            $queryFrom = $from;
            $queryTo   = $to;
        }

        $orders = DB::table('orders')
            ->leftJoin('users as w', 'orders.waiter_id', '=', 'w.id')
            ->leftJoin('users as c', 'orders.cashier_id', '=', 'c.id')
            ->select('orders.*', 'w.name as waiter_name', 'c.name as cashier_name')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$queryFrom, $queryTo])
            ->whereNotIn('orders.status', ['cancelled'])
            ->orderByDesc('orders.created_at')
            ->get();

        foreach ($orders as $order) {
            $order->item_count = DB::table('order_items')->where('order_id', $order->id)->count();
        }

        // Summary Stats
        $stats = [
            'total_orders'    => $orders->count(),
            'paid_orders'     => $orders->where('status', 'paid')->count(),
            'total_revenue'   => $orders->where('status', 'paid')->sum('total'),
            'refunded_amount' => $orders->sum('refund_amount'),
            'total_discounts' => $orders->sum('manual_discount'),
            'total_pieces'    => DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween(DB::raw('DATE(orders.created_at)'), [$queryFrom, $queryTo])
                ->where('orders.status', 'paid')
                ->sum('order_items.quantity'),
        ];
        $stats['avg_order_value'] = $stats['paid_orders'] > 0 ? round($stats['total_revenue'] / $stats['paid_orders'], 2) : 0;

        // Daily aggregated rows if range
        $rows = [];
        if ($type === 'range') {
            $rows = DB::table('orders')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as orders_count, SUM(CASE WHEN status="paid" THEN 1 ELSE 0 END) as paid_count, SUM(CASE WHEN status="paid" THEN total ELSE 0 END) as revenue')
                ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
                ->groupBy('date')
                ->orderByDesc('date')
                ->get();
        }

        // Top items
        $topItems = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('categories', 'order_items.category_id', '=', 'categories.id')
            ->selectRaw('order_items.item_name_ar as item_name_ar, categories.icon as cat_icon, SUM(order_items.quantity) as total_qty, SUM(order_items.unit_price * order_items.quantity) as total_revenue')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$queryFrom, $queryTo])
            ->where('orders.status', 'paid')
            ->groupBy('order_items.item_name_ar', 'categories.icon')
            ->orderByDesc('total_qty')
            ->limit(15)
            ->get();

        return view('admin.reports.index', compact('orders', 'stats', 'rows', 'topItems', 'type', 'date', 'from', 'to'));
    }
    public function exportNormal(Request $request)
    {
        return $this->generateExcel($request, 'summary');
    }

    public function exportDetailed(Request $request)
    {
        return $this->generateExcel($request, 'detailed');
    }

    public function exportItems(Request $request)
    {
        return $this->generateExcel($request, 'items');
    }

    private function generateExcel(Request $request, string $mode)
    {
        $from = $request->get('from', date('Y-m-d'));
        $to   = $request->get('to', date('Y-m-d'));

        $settings = DB::table('settings')->pluck('value', 'key')->toArray();
        $restName = $settings['restaurant_name'] ?? 'نظام مطعم فندق سبأ';

        if ($mode === 'items') {
            $itemsData = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from, $to])
                ->where('orders.status', 'paid')
                ->where('order_items.status', '!=', 'rejected')
                ->selectRaw('order_items.item_name_ar as item_name, SUM(order_items.quantity) as total_qty, SUM(order_items.unit_price * order_items.quantity) as total_revenue')
                ->groupBy('order_items.item_id', 'order_items.item_name_ar')
                ->orderByDesc('total_qty')
                ->get();

            $grandQty = $itemsData->sum('total_qty');
            $grandRev = $itemsData->sum('total_revenue');
            $rangeLabel = ($from === $to) ? $from : ($from . '_' . $to);
            $filename = 'تقرير_الاصناف_' . $rangeLabel . '.xls';

            $view = view('admin.reports.excel_items', compact('itemsData', 'restName', 'from', 'to', 'grandQty', 'grandRev'))->render();
        } else {
            $orders = DB::table('orders')
                ->leftJoin('users as w', 'orders.waiter_id', '=', 'w.id')
                ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from, $to])
                ->where('orders.status', '!=', 'cancelled')
                ->select('orders.*', 'w.name as waiter_name')
                ->orderBy('orders.id', 'asc')
                ->get();

            foreach ($orders as $order) {
                $order->items = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->orderBy('id', 'asc')
                    ->get();
            }

            $totalNet        = 0;
            $totalDiscount   = 0;
            $totalBeforeDisc = 0;
            foreach ($orders as $o) {
                $totalNet        += (float)$o->total - (float)($o->refund_amount ?? 0);
                $totalDiscount   += (float)($o->manual_discount ?? 0);
                $totalBeforeDisc += (float)$o->total + (float)($o->manual_discount ?? 0);
            }

            $rangeLabel = ($from === $to) ? $from : ($from . '_' . $to);
            $filename = 'تقرير_' . $rangeLabel . '_' . ($mode === 'detailed' ? 'تفصيلي' : 'عادي') . '.xls';

            $view = view('admin.reports.excel_orders', compact('orders', 'mode', 'restName', 'from', 'to', 'totalNet', 'totalDiscount', 'totalBeforeDisc'))->render();
        }

        return response($view)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . rawurlencode($filename) . '"')
            ->header('Cache-Control', 'no-cache');
    }
}
