<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $categories = DB::table('categories')
            ->selectRaw('categories.id, categories.name_ar, categories.name_en, categories.icon, categories.sort_order, categories.printer_id, categories.is_active, categories.created_at, COUNT(items.id) as items_count')
            ->leftJoin('items', 'items.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name_ar', 'categories.name_en', 'categories.icon', 'categories.sort_order', 'categories.printer_id', 'categories.is_active', 'categories.created_at')
            ->orderBy('categories.sort_order')
            ->paginate($limit);

        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name_ar' => 'required|string|max:150',
            'name_en' => 'required|string|max:150',
            'icon'    => 'nullable|string|max:100',
        ]);

        DB::table('categories')->insert([
            'name_ar'    => $request->name_ar,
            'name_en'    => $request->name_en,
            'icon'       => $request->icon ?? 'fas fa-utensils',
            'sort_order' => $request->sort_order ?? 0,
            'is_active'  => $request->has('is_active') ? 1 : 0,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'تمت إضافة الفئة بنجاح');
    }

    public function update(Request $request, int $category)
    {
        $request->validate([
            'name_ar' => 'required|string|max:150',
            'name_en' => 'required|string|max:150',
            'icon'    => 'nullable|string|max:100',
        ]);

        DB::table('categories')->where('id', $category)->update([
            'name_ar'    => $request->name_ar,
            'name_en'    => $request->name_en,
            'icon'       => $request->icon ?? 'fas fa-utensils',
            'sort_order' => $request->sort_order ?? 0,
            'is_active'  => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'تم تعديل الفئة بنجاح');
    }

    public function destroy(int $category)
    {
        $count = DB::table('items')->where('category_id', $category)->count();
        if ($count > 0) {
            return back()->with('error', "لا يمكن حذف الفئة لأنها تحتوي على $count صنف. احذف الأصناف أولاً.");
        }
        DB::table('categories')->where('id', $category)->delete();
        return redirect()->route('admin.categories.index')->with('success', 'تم حذف الفئة');
    }

    public function create() { return redirect()->route('admin.categories.index'); }
    public function edit(int $category) { return redirect()->route('admin.categories.index'); }
}
