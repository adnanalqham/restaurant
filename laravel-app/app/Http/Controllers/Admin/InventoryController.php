<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $items = DB::table('inv_items')->orderBy('name')->get();
        $purchases = DB::table('inv_purchases')
            ->join('inv_items', 'inv_purchases.item_id', '=', 'inv_items.id')
            ->leftJoin('users', 'inv_purchases.created_by', '=', 'users.id')
            ->select('inv_purchases.*', 'inv_items.name as item_name', 'inv_items.unit', 'users.name as user_name')
            ->orderByDesc('inv_purchases.created_at')
            ->paginate($limit);

        return view('admin.inventory.index', compact('items', 'purchases'));
    }

    public function storeItem(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'unit'      => 'required|string',
            'min_stock' => 'required|numeric|min:0',
        ]);

        DB::table('inv_items')->insert([
            'name'          => $request->name,
            'unit'          => $request->unit,
            'min_stock'     => $request->min_stock,
            'current_stock' => 0,
            'created_at'    => now(),
        ]);

        return redirect()->route('admin.inventory.index')->with('success', 'تمت إضافة الصنف للمخزن');
    }

    public function storePurchase(Request $request)
    {
        $request->validate([
            'item_id'  => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'price'    => 'required|numeric|min:0',
        ]);

        DB::table('inv_purchases')->insert([
            'item_id'    => $request->item_id,
            'quantity'   => $request->quantity,
            'price'      => $request->price,
            'supplier'   => $request->supplier,
            'notes'      => $request->notes,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // Update stock
        DB::table('inv_items')
            ->where('id', $request->item_id)
            ->increment('current_stock', $request->quantity);

        return redirect()->route('admin.inventory.index')->with('success', 'تم تسجيل المشتريات وتحديث المخزن');
    }

    public function report()
    {
        $items = DB::table('inv_items')->orderBy('name')->get();
        // No cost_per_unit in schema, so totalCost is 0
        $totalCost = 0;

        $lowStock = DB::table('inv_items')
            ->whereRaw('current_stock <= min_stock')
            ->orderBy('current_stock')
            ->get();

        return view('admin.inventory.report', compact('items', 'totalCost', 'lowStock'));
    }
}
