@extends('layouts.admin')

@section('title', 'إعدادات الحساب')

@section('content')
<style>
    .profile-container {
        padding: 20px;
        background: #f8fafc;
        min-height: calc(100vh - 70px);
        direction: rtl;
    }

    .header-box {
        background: #fff;
        padding: 15px 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        margin-bottom: 25px;
    }
    .header-box h2 { margin: 0; font-weight: 900; color: #333; font-size: 1.4rem; }

    .profile-card {
        max-width: 500px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .profile-card h3 { margin-top: 0; font-weight: 800; color: #444; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
    
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-weight: 700; color: #666; font-size: 0.9rem; margin-bottom: 8px; }
    .form-control { width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; background: #fdfdfd; }
    .form-control:focus { border-color: #e67e22; outline: none; box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.1); }

    .btn-save {
        width: 100%;
        background: #e67e22;
        color: #fff;
        border: none;
        padding: 12px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: 0.2s;
        margin-top: 25px;
    }
    .btn-save:hover { background: #d35400; transform: translateY(-1px); }

    .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 700; font-size: 0.9rem; }
    .alert-success { background: #eafaf1; color: #27ae60; border: 1px solid #d4efdf; }
    .alert-danger { background: #fdf2f2; color: #e74c3c; border: 1px solid #f9d6d6; }
</style>

<div class="profile-container">
    <div class="header-box">
        <h2><i class="fas fa-user-cog"></i> إعدادات الحساب</h2>
    </div>

    <div class="profile-card">
        <h3><i class="fas fa-key"></i> تغيير كلمة المرور</h3>
        <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee">

        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div><i class="fas fa-exclamation-circle"></i> {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('profile.password') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>كلمة المرور القديمة</label>
                <input type="password" name="old_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label>كلمة المرور الجديدة</label>
                <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>

            <div class="form-group">
                <label>تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="new_password_confirmation" class="form-control" minlength="6" required>
            </div>

            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> حفظ التغييرات
            </button>
        </form>
    </div>
</div>
@endsection
