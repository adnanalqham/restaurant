<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        $wallets = DB::table('wallets')->orderBy('sort_order')->paginate($limit);
        return view('admin.wallets.index', compact('wallets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
        ]);
        DB::table('wallets')->insert([
            'name'           => $request->name,
            'account_number' => $request->account_number,
            'sort_order'     => $request->sort_order ?? 0,
            'is_active'      => 1,
            'created_at'     => now(),
        ]);
        return back()->with('success', 'تم إضافة المحفظة بنجاح');
    }

    public function update(Request $request, int $wallet)
    {
        DB::table('wallets')->where('id', $wallet)->update([
            'name'           => $request->name,
            'account_number' => $request->account_number,
            'sort_order'     => $request->sort_order ?? 0,
            'is_active'      => $request->has('is_active') ? 1 : 0,
        ]);
        return back()->with('success', 'تم تعديل المحفظة');
    }

    public function destroy(int $wallet)
    {
        DB::table('wallets')->where('id', $wallet)->delete();
        return back()->with('success', 'تم حذف المحفظة');
    }
}
