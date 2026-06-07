<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserApiController extends Controller
{
    public function index()
    {
        $users = DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->select('users.*', 'roles.name as role_name', 'roles.name_ar as role_name_ar')
            ->orderByDesc('users.id')
            ->get();

        // Attach category permissions
        foreach ($users as $user) {
            $user->category_ids = DB::table('user_category_permissions')
                ->where('user_id', $user->id)
                ->pluck('category_id')
                ->toArray();
        }

        $roles = DB::table('roles')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => ['users' => $users, 'roles' => $roles]]);
    }

    public function store(Request $request)
    {
        if (!$request->name || !$request->username || !$request->password || !$request->role_id) {
            return response()->json(['success' => false, 'message' => 'يرجى تعبئة الحقول المطلوبة'], 400);
        }
        $exists = DB::table('users')->where('username', $request->username)->exists();
        if ($exists) return response()->json(['success' => false, 'message' => 'اسم المستخدم مستخدم مسبقاً'], 400);

        $perms = $request->permissions ? (array) $request->permissions : null;

        $id = DB::table('users')->insertGetId([
            'name'        => $request->name,
            'name_en'     => $request->name_en,
            'username'    => $request->username,
            'password'    => Hash::make($request->password),
            'role_id'     => $request->role_id,
            'is_active'   => 1,
            'can_print'   => $request->can_print ?? 1,
            'printer_mac' => $request->printer_mac,
            'permissions' => $perms ? json_encode($perms) : null,
        ]);

        return response()->json(['success' => true, 'message' => 'تمت إضافة المستخدم', 'data' => ['id' => $id]]);
    }

    public function update(Request $request, int $id)
    {
        if ($id == 1 && auth()->id() != 1) {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل المدير الرئيسي'], 403);
        }

        $exists = DB::table('users')->where('username', $request->username)->where('id', '!=', $id)->exists();
        if ($exists) return response()->json(['success' => false, 'message' => 'اسم المستخدم مستخدم مسبقاً'], 400);

        $data = [
            'name'        => $request->name,
            'name_en'     => $request->name_en,
            'username'    => $request->username,
            'role_id'     => $request->role_id,
            'is_active'   => $request->is_active ?? 1,
            'can_print'   => $request->can_print ?? 1,
            'printer_mac' => $request->printer_mac,
        ];

        if ($request->filled('password') && strlen($request->password) >= 4) {
            $data['password'] = Hash::make($request->password);
        }

        $perms = $request->permissions ? (array) $request->permissions : null;
        $data['permissions'] = $perms ? json_encode($perms) : null;

        DB::table('users')->where('id', $id)->update($data);

        return response()->json(['success' => true, 'message' => 'تم تحديث بيانات المستخدم']);
    }

    public function destroy(int $id)
    {
        if ($id == 1) return response()->json(['success' => false, 'message' => 'لا يمكن حذف المدير الرئيسي'], 403);
        if ($id == auth()->id()) return response()->json(['success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي'], 403);

        DB::table('users')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف المستخدم']);
    }

    public function savePermissions(Request $request)
    {
        $userId = $request->user_id;
        $catIds = $request->category_ids ?? [];

        DB::table('user_category_permissions')->where('user_id', $userId)->delete();
        foreach ($catIds as $catId) {
            DB::table('user_category_permissions')->insert(['user_id' => $userId, 'category_id' => $catId]);
        }

        return response()->json(['success' => true, 'message' => 'تم حفظ الصلاحيات بنجاح']);
    }
}
