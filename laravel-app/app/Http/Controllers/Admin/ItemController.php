<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ItemController extends Controller
{
    public function index()
    {
        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->select('items.*', 'categories.name_ar as cat_name')
            ->orderBy('categories.name_ar')
            ->orderBy('items.sort_order')
            ->get();

        $categories = DB::table('categories')->orderBy('name_ar')->get();
        $ingredients = DB::table('ingredients')->orderBy('name')->get();

        return view('admin.items.index', compact('items', 'categories', 'ingredients'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer',
            'name_ar'     => 'required|string|max:255',
            'name_en'     => 'required|string|max:255',
        ]);

        $imageName = '';
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '_' . $file->getClientOriginalName();
            // Move to the shared uploads directory
            $file->move(base_path('..' . DIRECTORY_SEPARATOR . 'uploads'), $imageName);
        }

        $id = DB::table('items')->insertGetId([
            'category_id'    => $request->category_id,
            'item_number'    => $request->item_number,
            'name_ar'        => $request->name_ar,
            'name_en'        => $request->name_en,
            'price'          => $request->price ?? 0,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image'          => $imageName,
            'sort_order'     => $request->sort_order ?? 0,
            'is_available'   => 1,
            'has_sizes'      => $request->has_sizes ? 1 : 0,
            'sizes'          => $request->sizes, // Expecting JSON string from JS
            'has_addons'     => $request->has_addons ? 1 : 0,
            'addons'         => $request->addons, // Expecting JSON string from JS
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->logAudit($id, 'create', 'all', null, $request->name_ar . ' - ' . $request->price);

        // Save Ingredients
        if ($request->filled('item_ingredients')) {
            $ings = json_decode($request->item_ingredients, true);
            if (is_array($ings)) {
                foreach ($ings as $ing) {
                    DB::table('item_ingredients')->insert([
                        'item_id'              => $id,
                        'ingredient_id'        => $ing['ingredient_id'],
                        'size_name'            => $ing['size_name'] ?? null,
                        'quantity_per_portion' => $ing['quantity_per_portion'],
                        'notes'                => $ing['notes'] ?? null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.items.index')->with('success', 'تمت إضافة الصنف بنجاح');
    }

    public function update(Request $request, int $item)
    {
        $request->validate([
            'category_id' => 'required|integer',
            'name_ar'     => 'required|string|max:255',
        ]);

        $old = DB::table('items')->find($item);
        if (!$old) return back()->with('error', 'الصنف غير موجود');

        $imageName = $old->image;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '_' . $file->getClientOriginalName();
            $file->move(base_path('..' . DIRECTORY_SEPARATOR . 'uploads'), $imageName);
            
            // Optionally delete old image
            $oldPath = base_path('..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $old->image;
            if ($old->image && File::exists($oldPath)) {
                File::delete($oldPath);
            }
        }

        DB::table('items')->where('id', $item)->update([
            'category_id'    => $request->category_id,
            'item_number'    => $request->item_number,
            'name_ar'        => $request->name_ar,
            'name_en'        => $request->name_en,
            'price'          => $request->price ?? 0,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'image'          => $imageName,
            'sort_order'     => $request->sort_order ?? $old->sort_order,
            'is_available'   => $request->has('is_available') ? 1 : 0,
            'has_sizes'      => $request->has_sizes ? 1 : 0,
            'sizes'          => $request->sizes,
            'has_addons'     => $request->has_addons ? 1 : 0,
            'addons'         => $request->addons,
            'updated_at'     => now(),
        ]);

        // Audit logs
        if ((float)$old->price !== (float)$request->price) {
            $this->logAudit($item, 'update', 'price', $old->price, $request->price);
        }
        if ($old->name_ar !== $request->name_ar) {
            $this->logAudit($item, 'update', 'name_ar', $old->name_ar, $request->name_ar);
        }

        // Update Ingredients
        if ($request->has('item_ingredients')) {
            DB::table('item_ingredients')->where('item_id', $item)->delete();
            $ings = json_decode($request->item_ingredients, true);
            if (is_array($ings)) {
                foreach ($ings as $ing) {
                    DB::table('item_ingredients')->insert([
                        'item_id'              => $item,
                        'ingredient_id'        => $ing['ingredient_id'],
                        'size_name'            => $ing['size_name'] ?? null,
                        'quantity_per_portion' => $ing['quantity_per_portion'],
                        'notes'                => $ing['notes'] ?? null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.items.index')->with('success', 'تم تعديل الصنف بنجاح');
    }

    public function destroy(int $item)
    {
        $old = DB::table('items')->find($item);
        if ($old && $old->image && File::exists(public_path('uploads/' . $old->image))) {
            File::delete(public_path('uploads/' . $old->image));
        }
        
        DB::table('items')->where('id', $item)->delete();
        if ($old) $this->logAudit($item, 'delete', 'all', $old->name_ar, null);

        return redirect()->route('admin.items.index')->with('success', 'تم حذف الصنف');
    }

    public function getIngredients(int $item)
    {
        $ings = DB::table('item_ingredients')
            ->where('item_id', $item)
            ->get();
        return response()->json(['success' => true, 'data' => $ings]);
    }

    private function logAudit(int $itemId, string $type, string $field, $old, $new)
    {
        try {
            DB::table('item_audit_log')->insert([
                'item_id'     => $itemId,
                'user_id'     => auth()->id(),
                'action_type' => $type,
                'field_name'  => $field,
                'old_value'   => $old,
                'new_value'   => $new,
                'created_at'  => now(),
            ]);
        } catch (\Exception $e) { /* silent */ }
    }
}
