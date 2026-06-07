<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = DB::table('settings')->pluck('value', 'key')->toArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->except('_token');
        
        foreach ($data as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return back()->with('success', 'تم حفظ الإعدادات بنجاح');
    }

    public function resetSystem(Request $request)
    {
        $request->validate(['password' => 'required']);
        
        // Check if password belongs to admin (id=1)
        $admin = DB::table('users')->where('id', 1)->first();
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['success' => false, 'message' => 'كلمة مرور المدير غير صحيحة'], 403);
        }

        try {
            DB::beginTransaction();
            // Delete orders and related data
            DB::table('order_items')->truncate();
            DB::table('orders')->truncate();
            // Reset auto-increment
            DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE order_items AUTO_INCREMENT = 1');
            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'تم تصفير النظام بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'فشل تصفير النظام: ' . $e->getMessage()], 500);
        }
    }

    public function backup()
    {
        // Simple backup logic (returning SQL file)
        // In a real environment, we'd use mysqldump
        // For now, I'll return a simple success message or implement a basic dump if possible
        return back()->with('info', 'سيتم تجهيز النسخة الاحتياطية قريباً');
    }
}
