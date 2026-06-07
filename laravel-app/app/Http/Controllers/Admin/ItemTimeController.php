<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemTimeController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', '7days');
        
        $query = DB::table('order_items as oi')
            ->join('items as i', 'oi.item_id', '=', 'i.id')
            ->selectRaw('
                i.name_ar, 
                i.name_en, 
                COUNT(oi.id) as times_prepared,
                AVG(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as avg_time_sec,
                MIN(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as min_time_sec,
                MAX(TIMESTAMPDIFF(SECOND, oi.prep_start_time, oi.prep_end_time)) as max_time_sec
            ')
            ->whereNotNull('oi.prep_start_time')
            ->whereNotNull('oi.prep_end_time')
            ->groupBy('i.id', 'i.name_ar', 'i.name_en')
            ->orderByDesc('avg_time_sec');

        if ($filter === 'today') {
            $query->whereDate('oi.prep_end_time', today());
        } elseif ($filter === '7days') {
            $query->where('oi.prep_end_time', '>=', now()->subDays(7));
        } elseif ($filter === '30days') {
            $query->where('oi.prep_end_time', '>=', now()->subDays(30));
        }

        $itemStats = $query->get();

        return view('admin.item_times.index', compact('itemStats', 'filter'));
    }
}
