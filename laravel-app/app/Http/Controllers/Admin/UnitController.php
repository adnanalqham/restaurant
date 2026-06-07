<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $units = DB::table('inv_units')->orderBy('name')->paginate($limit);
        return view('admin.units.index', compact('units'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:inv_units,name']);
        DB::table('inv_units')->insert([
            'name' => $request->name,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return back()->with('success', 'تم إضافة الوحدة بنجاح');
    }

    public function update(Request $request, int $unit)
    {
        $request->validate(['name' => 'required|unique:inv_units,name,'.$unit]);
        DB::table('inv_units')->where('id', $unit)->update([
            'name' => $request->name,
            'updated_at' => now()
        ]);
        return back()->with('success', 'تم تحديث الوحدة بنجاح');
    }

    public function destroy(int $unit)
    {
        // Check if unit is used in inv_items
        $unitName = DB::table('inv_units')->where('id', $unit)->value('name');
        $isUsed = DB::table('inv_items')->where('unit', $unitName)->exists();
        
        if ($isUsed) {
            return back()->with('error', 'لا يمكن حذف الوحدة لأنها مرتبطة بمكونات في المخزون');
        }

        DB::table('inv_units')->where('id', $unit)->delete();
        return back()->with('success', 'تم حذف الوحدة بنجاح');
    }
}
