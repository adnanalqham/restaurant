<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'يرجى إدخال اسم المستخدم',
            'password.required' => 'يرجى إدخال كلمة المرور',
        ]);

        // Fetch user by username (with role name via JOIN)
        $userData = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->select('users.*', 'roles.name as role_name', 'roles.name_ar as role_name_ar')
            ->where('users.username', $request->username)
            ->where('users.is_active', 1)
            ->first();

        if (!$userData || !password_verify($request->password, $userData->password)) {
            return back()
                ->withErrors(['error' => 'اسم المستخدم أو كلمة المرور غير صحيحة'])
                ->withInput(['username' => $request->username]);
        }

        // Load as Eloquent model for Auth
        $user = User::with('roleModel')->find($userData->id);
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return $this->redirectByRole($user);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ], [
            'old_password.required' => 'يرجى إدخال كلمة المرور القديمة',
            'new_password.required' => 'يرجى إدخال كلمة المرور الجديدة',
            'new_password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->old_password, $user->password)) {
            return back()->withErrors(['old_password' => 'كلمة المرور القديمة غير صحيحة']);
        }

        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        return back()->with('success', 'تم تغيير كلمة المرور بنجاح');
    }

    private function redirectByRole(User $user): \Illuminate\Http\RedirectResponse
    {
        $role = $user->getRoleName();

        return match(true) {
            in_array($role, ['admin', 'accountant', 'inventory_monitor', 'warehouse_manager', 'request_coordinator']) => redirect()->route('admin.dashboard'),
            $role === 'cashier'  => redirect()->route('cashier.index'),
            in_array($role, ['chef', 'juice_bar']) => redirect()->route('station.index'),
            default => redirect()->route('waiter.orders.create'),
        };
    }
}
