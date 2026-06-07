<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'نظام المطعم')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #e67e22;
            --secondary: #2c3e50;
            --bg: #f8f9fa;
            --text: #333;
            --border: #e0e0e0;
        }
        * { box-sizing: border-box; }
        body { 
            font-family: 'Tajawal', sans-serif; 
            margin: 0; background: var(--bg); color: var(--text);
            overflow: hidden; height: 100vh;
        }
    </style>
    @stack('styles')
</head>
<body>
    @yield('content')
    
    @stack('scripts')
</body>
</html>
