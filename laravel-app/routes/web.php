<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventoryRequestController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ItemAuditLogController;
use App\Http\Controllers\Admin\PrinterController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\ItemTimeController;
use App\Http\Controllers\Admin\SalesStatsController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\PrintController;
use App\Http\Controllers\Waiter\OrderController as WaiterOrderController;
use App\Http\Controllers\Waiter\InventoryRequestController as WaiterInventoryController;
use App\Http\Controllers\Cashier\DashboardController as CashierController;
use App\Http\Controllers\Station\DashboardController as StationController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\SSEController;

// ────────────────────────────────────────────────
// Auth
// ────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password');
});

// ────────────────────────────────────────────────
// Admin Panel
// ────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,accountant,inventory_monitor,warehouse_manager,request_coordinator,cashier'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::post('/categories/{category}/update', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Items (Menu)
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}/details', [AdminOrderController::class, 'details'])->name('orders.details');
    Route::post('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/deliver-all-active', [AdminOrderController::class, 'deliverAllActive'])->name('orders.deliver_all_active');
    Route::post('/orders/{order}/discount', [AdminOrderController::class, 'applyDiscount'])->name('orders.discount');
    Route::get('/orders/{order}/print', [AdminOrderController::class, 'print'])->name('orders.print');
    Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');

    // Users API (for AJAX)
    Route::get('/api/users', [UserApiController::class, 'index']);
    Route::post('/api/users', [UserApiController::class, 'store'])->name('users.store');
    Route::put('/api/users/{id}', [UserApiController::class, 'update']);
    Route::delete('/api/users/{id}', [UserApiController::class, 'destroy']);
    Route::post('/api/users/permissions', [UserApiController::class, 'savePermissions']);

    // Categories API (for AJAX)
    Route::get('/api/categories', [CategoryApiController::class, 'index']);

    // Dashboard API (for AJAX)
    Route::get('/api/dashboard/stats', [DashboardApiController::class, 'stats']);
    Route::get('/api/dashboard/charts', [DashboardApiController::class, 'charts']);

    // Wallets (Digital payment methods: STC Pay, etc.)
    Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::post('/wallets', [WalletController::class, 'store'])->name('wallets.store');
    Route::post('/wallets/{wallet}/update', [WalletController::class, 'update'])->name('wallets.update');
    Route::delete('/wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

    // Offers & Discounts
    Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
    Route::post('/offers/combo', [OfferController::class, 'storeCombo'])->name('offers.combo.store');
    Route::post('/offers/combo/{id}/toggle', [OfferController::class, 'toggleCombo'])->name('offers.combo.toggle');
    Route::delete('/offers/combo/{id}', [OfferController::class, 'destroyCombo'])->name('offers.combo.destroy');
    Route::post('/offers/discount', [OfferController::class, 'storeDiscount'])->name('offers.discount.store');
    Route::post('/offers/discount/{id}/toggle', [OfferController::class, 'toggleDiscount'])->name('offers.discount.toggle');
    Route::delete('/offers/discount/{id}', [OfferController::class, 'destroyDiscount'])->name('offers.discount.destroy');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/normal', [ReportController::class, 'exportNormal'])->name('reports.export.normal');
    Route::get('/reports/export/detailed', [ReportController::class, 'exportDetailed'])->name('reports.export.detailed');
    Route::get('/reports/export/items', [ReportController::class, 'exportItems'])->name('reports.export.items');
    Route::get('/sales-stats', [SalesStatsController::class, 'index'])->name('sales_stats.index');

    // Logs
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity_log.index');
    Route::get('/item-audit-logs', [ItemAuditLogController::class, 'index'])->name('item_audit_logs.index');
    Route::get('/item-times', [ItemTimeController::class, 'index'])->name('item_times.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/reset', [SettingsController::class, 'resetSystem'])->name('settings.reset');

    // Printers
    Route::get('/printers', [PrinterController::class, 'index'])->name('printers.index');
    Route::post('/printers', [PrinterController::class, 'store'])->name('printers.store');
    Route::post('/printers/{printer}/update', [PrinterController::class, 'update'])->name('printers.update');
    Route::delete('/printers/{printer}', [PrinterController::class, 'destroy'])->name('printers.destroy');

    // Print (ESC/POS via PowerShell)
    Route::post('/print/receipt/{orderId}', [PrintController::class, 'receipt'])->name('print.receipt');
    Route::post('/print/kitchen/{orderId}', [PrintController::class, 'kitchen'])->name('print.kitchen');
    Route::post('/print/all/{orderId}', [PrintController::class, 'all'])->name('print.all');
    Route::post('/print/test', [PrintController::class, 'test'])->name('print.test');

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('inventory.items.store');
    Route::post('/inventory/purchases', [InventoryController::class, 'storePurchase'])->name('inventory.purchases.store');
    Route::get('/inventory-report', [InventoryController::class, 'report'])->name('inventory.report');

    // Ingredients
    Route::get('/ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
    Route::post('/ingredients/{ingredient}/update', [IngredientController::class, 'update'])->name('ingredients.update');
    Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');

    // Units
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    // Inventory Requests
    Route::get('/inventory-requests', [InventoryRequestController::class, 'index'])->name('inventory.requests.index');
    Route::post('/inventory-requests/{request}/status', [InventoryRequestController::class, 'updateStatus'])->name('inventory.requests.status');
});

// ────────────────────────────────────────────────
// Waiter
// ────────────────────────────────────────────────
Route::prefix('waiter')->name('waiter.')->middleware(['auth', 'role:waiter,cashier,admin,chef,juice_bar'])->group(function () {
    Route::get('/', [WaiterOrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [WaiterOrderController::class, 'store'])->name('orders.store');
    Route::get('/orders', [WaiterOrderController::class, 'index'])->name('orders.index');
    Route::get('/inventory-requests', [WaiterInventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory-requests', [WaiterInventoryController::class, 'store'])->name('inventory.store');
});

// ────────────────────────────────────────────────
// Cashier
// ────────────────────────────────────────────────
Route::prefix('cashier')->name('cashier.')->middleware(['auth', 'role:cashier,admin'])->group(function () {
    Route::get('/', [CashierController::class, 'index'])->name('index');
});

// ────────────────────────────────────────────────
// Kitchen Station
// ────────────────────────────────────────────────
Route::prefix('station')->name('station.')->middleware(['auth', 'role:chef,juice_bar,admin'])->group(function () {
    Route::get('/', [StationController::class, 'index'])->name('index');
});

// ────────────────────────────────────────────────
// Root redirect
// ────────────────────────────────────────────────
Route::get('/', function () {
    if (!auth()->check()) return redirect()->route('login');
    $role = auth()->user()->getRoleName();
    return match(true) {
        in_array($role, ['admin', 'accountant', 'inventory_monitor', 'warehouse_manager', 'request_coordinator']) => redirect()->route('admin.dashboard'),
        $role === 'cashier'  => redirect()->route('cashier.index'),
        in_array($role, ['chef', 'juice_bar']) => redirect()->route('station.index'),
        default => redirect()->route('waiter.orders.create'),
    };
});

// ────────────────────────────────────────────────
// Shared AJAX APIs (Moved from api.php to maintain session)
// ────────────────────────────────────────────────
Route::middleware('auth')->prefix('api')->group(function () {
    // Orders
    Route::get('/orders',                [OrderApiController::class, 'index']);
    Route::post('/orders',               [OrderApiController::class, 'store']);
    Route::post('/orders/update-status',  [OrderApiController::class, 'updateStatus']);
    Route::post('/orders/prepare-all',    [OrderApiController::class, 'prepareAll']);
    Route::post('/orders/apply-discount', [OrderApiController::class, 'applyDiscount']);
    Route::post('/orders/delete-item',    [OrderApiController::class, 'deleteItem']);
    Route::get('/orders/{id}',            [OrderApiController::class, 'show']);
    Route::delete('/orders/{id}',         [OrderApiController::class, 'destroy']);

    // Items
    Route::post('/items/update-status', [OrderApiController::class, 'updateItemStatus']);

    // SSE
    Route::get('/sse', [SSEController::class, 'stream']);

    // Users JSON API (for AJAX)
    Route::get('/users',             [UserApiController::class, 'index']);
    Route::post('/users',            [UserApiController::class, 'store']);
    Route::put('/users/{id}',         [UserApiController::class, 'update']);
    Route::delete('/users/{id}',      [UserApiController::class, 'destroy']);
    Route::post('/users/permissions', [UserApiController::class, 'savePermissions']);

    // Categories JSON API
    Route::get('/categories', [CategoryApiController::class, 'index']);
});
