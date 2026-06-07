<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; margin-bottom: 32px; }
        .logo i { font-size: 3rem; color: #f39c12; margin-bottom: 12px; display: block; }
        .logo h1 { color: #fff; font-size: 1.5rem; font-weight: 700; }
        .logo p { color: rgba(255,255,255,0.5); font-size: 0.9rem; margin-top: 4px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: rgba(255,255,255,0.8); margin-bottom: 8px; font-size: 0.9rem; }
        input {
            width: 100%; padding: 12px 16px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px; color: #fff; font-size: 1rem;
            font-family: 'Tajawal', sans-serif; transition: all 0.3s;
        }
        input:focus { outline: none; border-color: #f39c12; background: rgba(255,255,255,0.12); }
        input::placeholder { color: rgba(255,255,255,0.3); }
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border: none; border-radius: 10px; color: #fff;
            font-size: 1rem; font-weight: 700; font-family: 'Tajawal', sans-serif;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(243,156,18,0.4); }
        .error-msg {
            background: rgba(231,76,60,0.2); border: 1px solid rgba(231,76,60,0.4);
            color: #e74c3c; padding: 12px 16px; border-radius: 10px;
            margin-bottom: 20px; font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <i class="fas fa-utensils"></i>
            <h1>{{ config('app.name') }}</h1>
            <p>نظام إدارة المطاعم المتكامل</p>
        </div>

        @if ($errors->has('error'))
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> اسم المستخدم</label>
                <input type="text" id="username" name="username" 
                       value="{{ old('username') }}" 
                       placeholder="أدخل اسم المستخدم" required autofocus>
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> كلمة المرور</label>
                <input type="password" id="password" name="password" 
                       placeholder="أدخل كلمة المرور" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </button>
        </form>
    </div>
</body>
</html>
