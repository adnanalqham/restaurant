<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrinterController extends Controller
{
    public function index()
    {
        $printers   = DB::table('printers')->orderBy('id')->get();
        $categories = DB::table('categories')->orderBy('name_ar')->get();
        return view('admin.printers.index', compact('printers', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'type'         => 'required|in:cashier,kitchen,bar,grill,shisha',
            'windows_name' => 'required|string|max:200',
        ]);

        DB::table('printers')->insert([
            'name'         => $request->name,
            'windows_name' => $request->windows_name,
            'ip'           => $request->ip_address ?? '',
            'port'         => $request->port ?? 9100,
            'type'         => $request->type,
            'category_ids' => $request->category_ids ? json_encode($request->category_ids) : null,
            'created_at'   => now(),
        ]);

        return back()->with('success', 'تم إضافة الطابعة بنجاح');
    }

    public function update(Request $request, int $printer)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'type'         => 'required|in:cashier,kitchen,bar,grill,shisha',
            'windows_name' => 'required|string|max:200',
        ]);

        DB::table('printers')->where('id', $printer)->update([
            'name'         => $request->name,
            'windows_name' => $request->windows_name,
            'ip'           => $request->ip_address ?? '',
            'port'         => $request->port ?? 9100,
            'type'         => $request->type,
            'category_ids' => $request->category_ids ? json_encode($request->category_ids) : null,
        ]);

        return back()->with('success', 'تم تعديل الطابعة بنجاح');
    }

    public function destroy(int $printer)
    {
        DB::table('printers')->where('id', $printer)->delete();
        return back()->with('success', 'تم حذف الطابعة');
    }
}
