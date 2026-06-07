<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    public function index()
    {
        // 1. Combos (Offers)
        $combos = DB::table('offers')->orderByDesc('created_at')->get();
        foreach($combos as $c) {
            $c->items = DB::table('offer_items')
                ->join('items', 'offer_items.item_id', '=', 'items.id')
                ->where('offer_id', $c->id)
                ->select('offer_items.*', 'items.name_ar as item_name')
                ->get();
        }

        // 2. Discounts (Item/Category)
        $discounts = DB::table('discounts')->orderByDesc('created_at')->get();
        foreach($discounts as $d) {
            if ($d->type === 'item') {
                $d->target_name = DB::table('items')->where('id', $d->target_id)->value('name_ar') ?: 'محذوف';
            } else {
                $d->target_name = DB::table('categories')->where('id', $d->target_id)->value('name_ar') ?: 'محذوف';
            }
        }

        $items = DB::table('items')->where('is_available', 1)->orderBy('name_ar')->get();
        $categories = DB::table('categories')->orderBy('name_ar')->get();

        return view('admin.offers.index', compact('combos', 'discounts', 'items', 'categories'));
    }
    public function storeCombo(Request $request)
    {
        $request->validate(['name_ar'=>'required', 'price'=>'required|numeric']);
        
        $id = DB::table('offers')->insertGetId([
            'name_ar'    => $request->name_ar,
            'price'      => $request->price,
            'is_active'  => 1,
            'created_at' => now(),
        ]);

        if ($request->items) {
            foreach($request->items as $item) {
                DB::table('offer_items')->insert([
                    'offer_id' => $id,
                    'item_id'  => $item['id'],
                    'quantity' => $item['qty'],
                ]);
            }
        }

        return back()->with('success', 'تم إضافة الباقة بنجاح');
    }

    public function toggleCombo(Request $request, int $id)
    {
        DB::table('offers')->where('id', $id)->update(['is_active' => $request->status]);
        return back()->with('success', 'تم تحديث حالة الباقة');
    }

    public function storeDiscount(Request $request)
    {
        $request->validate(['type'=>'required','target_id'=>'required','discount_type'=>'required','discount_value'=>'required']);
        
        // Check duplicate
        $exists = DB::table('discounts')
            ->where('type', $request->type)
            ->where('target_id', $request->target_id)
            ->where('is_active', 1)
            ->first();
            
        if ($exists) return back()->with('error', 'يوجد خصم مفعل مسبقاً لهذا العنصر');

        DB::table('discounts')->insert([
            'type'           => $request->type,
            'target_id'      => $request->target_id,
            'discount_type'  => $request->discount_type,
            'discount_value' => $request->discount_value,
            'is_active'      => 1,
            'created_at'     => now(),
        ]);

        return back()->with('success', 'تم تطبيق الخصم بنجاح');
    }

    public function toggleDiscount(Request $request, int $id)
    {
        DB::table('discounts')->where('id', $id)->update(['is_active' => $request->status]);
        return back()->with('success', 'تم تحديث حالة الخصم');
    }

    public function destroyCombo(int $id)
    {
        DB::table('offer_items')->where('offer_id', $id)->delete();
        DB::table('offers')->where('id', $id)->delete();
        return back()->with('success', 'تم حذف الباقة');
    }

    public function destroyDiscount(int $id)
    {
        DB::table('discounts')->where('id', $id)->delete();
        return back()->with('success', 'تم حذف الخصم');
    }
}
