<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $ingredients = DB::table('inv_items')->orderBy('name')->paginate($limit);
        $units = DB::table('inv_units')->orderBy('name')->get();
        return view('admin.ingredients.index', compact('ingredients', 'units'));
    }
    public function store(Request $request)
    {
        $request->validate(['name'=>'required','unit'=>'required']);
        DB::table('inv_items')->insert(['name'=>$request->name,'unit'=>$request->unit,'min_stock'=>$request->min_stock??0,'current_stock'=>0,'cost_per_unit'=>$request->cost_per_unit??0,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','تم إضافة المكون');
    }
    public function update(Request $request, int $ingredient)
    {
        $request->validate(['name'=>'required']);
        DB::table('inv_items')->where('id',$ingredient)->update(['name'=>$request->name,'unit'=>$request->unit??'','min_stock'=>$request->min_stock??0,'cost_per_unit'=>$request->cost_per_unit??0,'updated_at'=>now()]);
        return back()->with('success','تم تعديل المكون');
    }
    public function destroy(int $ingredient)
    {
        DB::table('inv_items')->where('id',$ingredient)->delete();
        return back()->with('success','تم حذف المكون');
    }
}
