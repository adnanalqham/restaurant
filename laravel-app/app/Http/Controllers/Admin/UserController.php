<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->select('users.*', 'roles.name as role_name', 'roles.name_ar as role_name_ar')
            ->orderBy('users.name')
            ->get();

        $roles = DB::table('roles')->orderBy('id')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:4',
            'role_id'  => 'required|integer',
        ]);

        DB::table('users')->insert([
            'name'      => $request->name,
            'username'  => $request->username,
            'password'  => Hash::make($request->password),
            'role_id'   => $request->role_id,
            'phone'     => $request->phone,
            'is_active' => 1,
            'can_print' => 1,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'تمت إضافة المستخدم بنجاح');
    }

    public function update(Request $request, int $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,'.$user,
            'role_id'  => 'required|integer',
        ]);

        $data = [
            'name'      => $request->name,
            'username'  => $request->username,
            'role_id'   => $request->role_id,
            'phone'     => $request->phone,
            'is_active' => $request->has('is_active') ? 1 : 0,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        DB::table('users')->where('id', $user)->update($data);

        return redirect()->route('admin.users.index')->with('success', 'تم تحديث بيانات المستخدم');
    }

    public function destroy(int $user)
    {
        if ($user === auth()->id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي');
        }
        DB::table('users')->where('id', $user)->delete();
        return redirect()->route('admin.users.index')->with('success', 'تم حذف المستخدم');
    }
}
