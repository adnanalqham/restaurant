<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'لوحة التحكم') | {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=1.5">
    @stack('styles')
</head>
<body>
<div class="app-wrapper">

    <!-- ===== Sidebar ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="{{ route('admin.dashboard') }}" class="brand" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-utensils brand-icon"></i>
                <div>
                    <div class="brand-name">{{ config('app.name') }}</div>
                    <div class="brand-sub">لوحة الإدارة</div>
                </div>
            </a>
        </div>

        <div class="user-card">
            <div class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
            <div class="user-info">
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">{{ auth()->user()->roleModel?->name_ar ?? auth()->user()->getRoleName() }}</div>
            </div>
        </div>

        <nav class="sidebar-nav">
        @php
            $role  = auth()->user()->getRoleName();
            $perms = auth()->user()->permissions ?? [];
            $isAdmin     = $role === 'admin';
            $isAccountant= $role === 'accountant';
            $isCashier   = $role === 'cashier';
            $isInvMon    = $role === 'inventory_monitor';
            $isWH        = $role === 'warehouse_manager';
            $isReqCoord  = $role === 'request_coordinator';
            $hasInvAccess= $isAdmin || $isInvMon || $isWH || $isReqCoord || in_array('inventory', $perms);
        @endphp

        {{-- ─── Cashier Quick Links ─── --}}
        @if($isCashier)
        <a href="{{ route('cashier.index') }}" class="nav-item {{ request()->routeIs('cashier.index') ? 'active' : '' }}">
            <i class="fas fa-cash-register"></i><span>مراقبة الطلبات</span>
        </a>
        <a href="{{ route('waiter.orders.create') }}" class="nav-item {{ request()->routeIs('waiter.orders.create') ? 'active' : '' }}">
            <i class="fas fa-plus-circle"></i><span>طلب جديد</span>
        </a>
        <a href="{{ route('waiter.orders.index') }}" class="nav-item {{ request()->routeIs('waiter.orders.index') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i><span>طلباتي</span>
        </a>
        <a href="{{ route('admin.reports.index') }}" class="nav-item {{ request()->routeIs('admin.reports.index') ? 'active' : '' }}">
            <i class="fas fa-file-invoice-dollar"></i><span>التقارير اليومية</span>
        </a>
        <a href="{{ route('admin.item_times.index') }}" class="nav-item {{ request()->routeIs('admin.item_times.index') ? 'active' : '' }}">
            <i class="fas fa-stopwatch"></i><span>أوقات الأصناف</span>
        </a>
        <hr style="border:none; border-top:1px solid rgba(255,255,255,0.1); margin:10px 15px;">
        @endif

        {{-- ─── Dashboard ─── --}}
        @if($isAdmin || $isAccountant || in_array('dashboard', $perms))
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i><span>لوحة التحكم</span>
        </a>
        @endif

        {{-- ─── القائمة ─── --}}
        @if($isAdmin || in_array('categories', $perms) || in_array('items', $perms))
        <div class="nav-group {{ request()->routeIs('admin.categories.*','admin.items.*') ? 'open' : '' }}">
            <div class="nav-group-header"><i class="fas fa-utensils"></i><span>القائمة</span><i class="fas fa-chevron-down"></i></div>
            <div class="nav-group-body">
                @if($isAdmin || in_array('categories',$perms))
                <a href="{{ route('admin.categories.index') }}" class="nav-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"><i class="fas fa-folder"></i><span>الفئات</span></a>
                @endif
                @if($isAdmin || in_array('items',$perms))
                <a href="{{ route('admin.items.index') }}" class="nav-item {{ request()->routeIs('admin.items.*') ? 'active' : '' }}"><i class="fas fa-hamburger"></i><span>الأصناف</span></a>
                @endif
            </div>
        </div>
        @endif

        {{-- ─── العمليات ─── --}}
        @if($isAdmin || $isAccountant || in_array('wallets',$perms) || in_array('orders',$perms) || in_array('offers',$perms))
        <div class="nav-group {{ request()->routeIs('admin.wallets.*','admin.orders.*','admin.offers.*') ? 'open' : '' }}">
            <div class="nav-group-header"><i class="fas fa-receipt"></i><span>العمليات</span><i class="fas fa-chevron-down"></i></div>
            <div class="nav-group-body">
                @if($isAdmin || in_array('wallets',$perms))
                <a href="{{ route('admin.wallets.index') }}" class="nav-item {{ request()->routeIs('admin.wallets.*') ? 'active' : '' }}"><i class="fas fa-wallet"></i><span>المحافظ الرقمية</span></a>
                @endif
                @if($isAdmin || $isAccountant || in_array('orders',$perms))
                <a href="{{ route('admin.orders.index') }}" class="nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i><span>إدارة الطلبات</span></a>
                @endif
                @if($isAdmin || in_array('offers',$perms))
                <a href="{{ route('admin.offers.index') }}" class="nav-item {{ request()->routeIs('admin.offers.*') ? 'active' : '' }}"><i class="fas fa-gift"></i><span>العروض والتخفيضات</span></a>
                @endif
            </div>
        </div>
        @endif

        {{-- ─── إدارة النظام ─── --}}
        @if($isAdmin || $isAccountant || in_array('users',$perms) || in_array('reports',$perms) || in_array('activity_log',$perms) || in_array('item_audit_logs',$perms) || in_array('printers',$perms) || in_array('settings',$perms))
        <div class="nav-group {{ request()->routeIs('admin.users.*','admin.reports.*','admin.activity_log.*','admin.item_audit_logs.*','admin.printers.*','admin.settings.*','admin.sales_stats.*') ? 'open' : '' }}">
            <div class="nav-group-header"><i class="fas fa-cogs"></i><span>إدارة النظام</span><i class="fas fa-chevron-down"></i></div>
            <div class="nav-group-body">
                @if($isAdmin || in_array('users',$perms))
                <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><i class="fas fa-users-cog"></i><span>المستخدمون</span></a>
                @endif
                @if($isAdmin || $isAccountant || in_array('reports',$perms))
                <a href="{{ route('admin.reports.index') }}" class="nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"><i class="fas fa-file-invoice-dollar"></i><span>التقارير المالية</span></a>
                @endif
                @if($isAdmin || $isAccountant || in_array('activity_log',$perms))
                <a href="{{ route('admin.activity_log.index') }}" class="nav-item {{ request()->routeIs('admin.activity_log.*') ? 'active' : '' }}"><i class="fas fa-history"></i><span>مراقبة النظام</span></a>
                @endif
                @if($isAdmin || $isAccountant || in_array('item_audit_logs',$perms))
                <a href="{{ route('admin.item_audit_logs.index') }}" class="nav-item {{ request()->routeIs('admin.item_audit_logs.*') ? 'active' : '' }}"><i class="fas fa-search-dollar"></i><span>مراقبة الأسعار</span></a>
                @endif
                @if($isAdmin || in_array('item_times',$perms))
                <a href="{{ route('admin.item_times.index') }}" class="nav-item {{ request()->routeIs('admin.item_times.*') ? 'active' : '' }}"><i class="fas fa-stopwatch"></i><span>أوقات التحضير</span></a>
                @endif
                @if($isAdmin || in_array('printers',$perms))
                <a href="{{ route('admin.printers.index') }}" class="nav-item {{ request()->routeIs('admin.printers.*') ? 'active' : '' }}"><i class="fas fa-print"></i><span>إعدادات الطابعات</span></a>
                @endif
                @if($isAdmin || in_array('settings',$perms))
                <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><i class="fas fa-cog"></i><span>الإعدادات</span></a>
                @endif
            </div>
        </div>
        @endif

        {{-- ─── إدارة المخزون ─── --}}
        @if($hasInvAccess)
        <div class="nav-group {{ request()->routeIs('admin.ingredients.*','admin.inventory.*','admin.sales_stats.*') ? 'open' : '' }}">
            <div class="nav-group-header"><i class="fas fa-boxes"></i><span>إدارة المخزون</span><i class="fas fa-chevron-down"></i></div>
            <div class="nav-group-body">
                @if($isAdmin || $isInvMon || in_array('ingredients',$perms))
                <a href="{{ route('admin.ingredients.index') }}" class="nav-item {{ request()->routeIs('admin.ingredients.*') ? 'active' : '' }}"><i class="fas fa-boxes"></i><span>المكونات</span></a>
                @endif
                @if($isAdmin || in_array('units',$perms))
                <a href="{{ route('admin.units.index') }}" class="nav-item {{ request()->routeIs('admin.units.*') ? 'active' : '' }}"><i class="fas fa-balance-scale"></i><span>الوحدات</span></a>
                @endif
                @if($isAdmin || $isInvMon || $isWH || in_array('inventory',$perms))
                <a href="{{ route('admin.inventory.index') }}" class="nav-item {{ request()->routeIs('admin.inventory.index') ? 'active' : '' }}"><i class="fas fa-clipboard-check"></i><span>إدخال المخزون</span></a>
                @endif
                @if($isAdmin || $isInvMon || $isWH || in_array('inventory_report',$perms))
                <a href="{{ route('admin.inventory.report') }}" class="nav-item {{ request()->routeIs('admin.inventory.report') ? 'active' : '' }}"><i class="fas fa-balance-scale"></i><span>تقرير المخزون</span></a>
                @endif
                @if($isAdmin || $isInvMon || in_array('sales_stats',$perms))
                <a href="{{ route('admin.sales_stats.index') }}" class="nav-item {{ request()->routeIs('admin.sales_stats.*') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i><span>إحصائيات المبيعات</span></a>
                @endif
                @if($isAdmin || $isWH || $isReqCoord || in_array('inventory_requests_manage',$perms))
                <a href="{{ route('admin.inventory.requests.index') }}" class="nav-item {{ request()->routeIs('admin.inventory.requests.*') ? 'active' : '' }}"><i class="fas fa-tasks"></i><span>طلبات الصرف</span></a>
                @endif
            </div>
        </div>
        @endif

        </nav>

        <form method="POST" action="{{ route('logout') }}" class="sidebar-footer">
            @csrf
            <button type="submit" class="btn-logout"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</button>
        </form>
    </aside>

    <!-- ===== Main Content ===== -->
    <main class="main-content">
        <header class="topbar">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">@yield('title', 'لوحة التحكم')</h1>
            <div class="topbar-actions">@yield('topbar-actions')</div>
        </header>

        @if(session('success'))
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $err)<div>• {{ $err }}</div>@endforeach
        </div>
        @endif

        <div class="page-content">
            @yield('content')
        </div>
    </main>
</div>

<script>
document.querySelectorAll('.nav-group-header').forEach(h => {
    h.addEventListener('click', () => h.parentElement.classList.toggle('open'));
});
window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
function apiPost(url, data, method='POST') {
    return fetch(url, {
        method,
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':window.CSRF_TOKEN},
        body: JSON.stringify(data)
    }).then(r=>r.json());
}
</script>
@stack('scripts')
</body>
</html>
