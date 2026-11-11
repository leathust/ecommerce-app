<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminBrandController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminContactController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\AdminMenuController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminSystemSettingController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CatalogImageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ElementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductImageController;
use Illuminate\Support\Facades\Route;

// User routes
Route::get('/media/catalog/{type}/{id}', [CatalogImageController::class, 'show'])->whereIn('type', ['categories', 'brands'])->whereNumber('id')->name('catalog-images.show');
Route::get('/media/product-images/{image}', [ProductImageController::class, 'show'])->name('product-images.show');
Route::get('/', [HomeController::class, 'home'])->name('home');
Route::get('/cart', [HomeController::class, 'cart'])->name('cart');
Route::get('/shop', [HomeController::class, 'shop'])->name('shop');
Route::get('/products', [HomeController::class, 'products'])->name('products');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact-submissions')
    ->name('contact.store');
Route::get('/track-order', [HomeController::class, 'trackOrder'])->name('track-order');
Route::get('/help', [HomeController::class, 'help'])->name('help');
Route::get('/language', [HomeController::class, 'language'])->name('language');
Route::get('/account', [HomeController::class, 'account'])->name('account');

// Admin routes
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware(['auth', 'active', 'admin'])->group(function (): void {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('brands', [AdminBrandController::class, 'index'])->middleware('permission:brands.view')->name('brands.index');
        Route::get('brands/create', [AdminBrandController::class, 'create'])->middleware('permission:brands.create')->name('brands.create');
        Route::post('brands', [AdminBrandController::class, 'store'])->middleware('permission:brands.create')->name('brands.store');
        Route::get('brands/{brand}/edit', [AdminBrandController::class, 'edit'])->middleware('permission:brands.update')->name('brands.edit');
        Route::put('brands/{brand}', [AdminBrandController::class, 'update'])->middleware('permission:brands.update')->name('brands.update');
        Route::delete('brands/{brand}', [AdminBrandController::class, 'destroy'])->middleware('permission:brands.delete')->name('brands.destroy');
        Route::get('categories', [AdminCategoryController::class, 'index'])->middleware('permission:categories.view')->name('categories.index');
        Route::get('categories/create', [AdminCategoryController::class, 'create'])->middleware('permission:categories.create')->name('categories.create');
        Route::post('categories', [AdminCategoryController::class, 'store'])->middleware('permission:categories.create')->name('categories.store');
        Route::get('categories/{category}/edit', [AdminCategoryController::class, 'edit'])->middleware('permission:categories.update')->name('categories.edit');
        Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->middleware('permission:categories.update')->name('categories.update');
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->middleware('permission:categories.delete')->name('categories.destroy');
        Route::get('menus', [AdminMenuController::class, 'index'])->middleware('permission:menus.view')->name('menus.index');
        Route::get('menus/create', [AdminMenuController::class, 'create'])->middleware('permission:menus.create')->name('menus.create');
        Route::post('menus', [AdminMenuController::class, 'store'])->middleware('permission:menus.create')->name('menus.store');
        Route::get('menus/{menu}/move', [AdminMenuController::class, 'moveForm'])->middleware('permission:menus.update')->name('menus.move.form');
        Route::patch('menus/{menu}/move', [AdminMenuController::class, 'move'])->middleware('permission:menus.update')->name('menus.move');
        Route::get('menus/{menu}/edit', [AdminMenuController::class, 'edit'])->middleware('permission:menus.update')->name('menus.edit');
        Route::put('menus/{menu}', [AdminMenuController::class, 'update'])->middleware('permission:menus.update')->name('menus.update');
        Route::patch('menus/reorder', [AdminMenuController::class, 'reorder'])->middleware('permission:menus.update')->name('menus.reorder');
        Route::patch('menus/{menu}/status', [AdminMenuController::class, 'updateStatus'])->middleware('permission:menus.update')->name('menus.status.update');
        Route::delete('menus/{menu}', [AdminMenuController::class, 'destroy'])->middleware('permission:menus.delete')->name('menus.destroy');

        Route::middleware('permission:dashboard.view')->group(function (): void {
            Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('dashboard2', [AdminController::class, 'dashboard2'])->name('dashboard2');
            Route::get('dashboard3', [AdminController::class, 'dashboard3'])->name('dashboard3');
            Route::get('dashboard4', [AdminController::class, 'dashboard4'])->name('dashboard4');
            Route::get('charts', [AdminController::class, 'charts'])->name('charts');
            Route::get('tables', [AdminController::class, 'tables'])->name('tables');
            Route::get('data-tables', [AdminController::class, 'dataTables'])->name('data-tables');
            Route::get('forms/form', [AdminController::class, 'forms'])->name('forms.form');
            Route::get('forms/wizard', [AdminController::class, 'wizards'])->name('forms.wizard');
            Route::get('calendar', [AdminController::class, 'calendar'])->name('calendar');
            Route::get('maps', [AdminController::class, 'maps'])->name('maps');
            Route::get('inbox', [AdminController::class, 'inbox'])->name('inbox');
            Route::get('kanban', [AdminController::class, 'kanban'])->name('kanban');
            Route::get('notifications', [AdminController::class, 'notifications'])->name('notifications');
            Route::get('documentation', [AdminController::class, 'documentation'])->name('documentation');
        });

        Route::get('products', [AdminProductController::class, 'index'])
            ->middleware('permission:products.view')
            ->name('products');
        Route::get('products/create', [AdminProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
        Route::post('products', [AdminProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
        Route::post('products/bulk-delete', [AdminProductController::class, 'bulkDelete'])->middleware('permission:products.delete')->name('products.bulk-delete');
        Route::get('products/{product}/edit', [AdminProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
        Route::put('products/{product}', [AdminProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
        Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
        Route::get('orders', [AdminOrderController::class, 'index'])
            ->middleware('permission:orders.view')
            ->name('orders');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])
            ->middleware('permission:orders.view')
            ->name('orders.show');
        Route::get('orders/{order}/print', [AdminOrderController::class, 'printInvoice'])
            ->middleware('permission:orders.view')
            ->name('orders.print');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
            ->middleware('permission:orders.update|orders.cancel')
            ->name('orders.status.update');
        Route::get('customers', [AdminCustomerController::class, 'index'])
            ->middleware('permission:customers.view')
            ->name('customers');
        Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])
            ->middleware('permission:customers.view')
            ->name('customers.show');
        Route::patch('customers/{customer}/status', [AdminCustomerController::class, 'updateStatus'])
            ->middleware('permission:customers.update')
            ->name('customers.status.update');
        Route::get('users', [AdminUserController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])
            ->middleware('permission:users.create')
            ->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])
            ->middleware('permission:users.update')
            ->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');
        Route::get('users/{user}', [AdminUserController::class, 'show'])
            ->middleware('permission:users.view')
            ->name('users.show');
        Route::get('roles', [AdminRoleController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('roles.index');
        Route::get('roles/create', [AdminRoleController::class, 'create'])
            ->middleware('permission:roles.manage')
            ->name('roles.create');
        Route::post('roles', [AdminRoleController::class, 'store'])
            ->middleware('permission:roles.manage')
            ->name('roles.store');
        Route::get('roles/{role}/edit', [AdminRoleController::class, 'edit'])
            ->middleware('permission:roles.manage')
            ->name('roles.edit');
        Route::put('roles/{role}', [AdminRoleController::class, 'update'])
            ->middleware('permission:roles.manage')
            ->name('roles.update');
        Route::put('roles/{role}/permissions', [AdminRoleController::class, 'updatePermissions'])
            ->middleware('permission:permissions.manage')
            ->name('roles.permissions.update');
        Route::delete('roles/{role}', [AdminRoleController::class, 'destroy'])
            ->middleware('permission:roles.manage')
            ->name('roles.destroy');
        Route::get('access-logs', [AdminAuditLogController::class, 'index'])
            ->middleware('permission:permissions.manage')
            ->name('access-logs.index');
        Route::get('access-logs/{auditLog}', [AdminAuditLogController::class, 'show'])
            ->middleware('permission:permissions.manage')
            ->name('access-logs.show');
        Route::get('settings', [AdminSystemSettingController::class, 'index'])
            ->middleware('permission:settings.view')
            ->name('settings.index');
        Route::put('settings/{group}', [AdminSystemSettingController::class, 'update'])
            ->middleware('permission:settings.update')
            ->name('settings.update');
        Route::get('contacts', [AdminContactController::class, 'index'])
            ->middleware('permission:contacts.view')
            ->name('contacts.index');
        Route::patch('contacts/{contact}/status', [AdminContactController::class, 'updateStatus'])
            ->middleware('permission:contacts.update')
            ->name('contacts.status.update');
        Route::post('contacts/{contact}/reply', [AdminContactController::class, 'reply'])
            ->middleware(['permission:contacts.update', 'throttle:10,1'])
            ->name('contacts.reply');
    });
});

// Account routes
Route::middleware(['auth', 'active', 'admin', 'permission:dashboard.view'])->group(function (): void {
    Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/account/pricing', [AccountController::class, 'pricing'])->name('account.pricing');
    Route::get('/account/invoice', [AccountController::class, 'invoice'])->name('account.invoice');
});

// Page routes
Route::get('/pages/login', [PageController::class, 'login'])->name('pages.login');
Route::get('/pages/register', [PageController::class, 'register'])->name('pages.register');
Route::get('/pages/forget-password', [PageController::class, 'forgetPassword'])->name('pages.forget-pass');
Route::get('/pages/404', [PageController::class, 'page404'])->name('pages.404');
Route::get('/pages/500', [PageController::class, 'page500'])->name('pages.500');
Route::get('/pages/maintenance', [PageController::class, 'maintenance'])->name('pages.maintenance');

// Elements routes
Route::middleware(['auth', 'active', 'admin', 'permission:dashboard.view'])->group(function (): void {
    Route::get('/elements/buttons', [ElementController::class, 'buttons'])->name('elements.buttons');
    Route::get('/elements/badges', [ElementController::class, 'badges'])->name('elements.badges');
    Route::get('/elements/tabs', [ElementController::class, 'tabs'])->name('elements.tabs');
    Route::get('/elements/cards', [ElementController::class, 'cards'])->name('elements.cards');
    Route::get('/elements/alerts', [ElementController::class, 'alerts'])->name('elements.alerts');
    Route::get('/elements/progress-bars', [ElementController::class, 'progressBars'])->name('elements.progress-bars');
    Route::get('/elements/modals', [ElementController::class, 'modals'])->name('elements.modals');
    Route::get('/elements/switches', [ElementController::class, 'switches'])->name('elements.switches');
    Route::get('/elements/grid-system', [ElementController::class, 'gridSystem'])->name('elements.grid-system');
    Route::get('/elements/font-awesome', [ElementController::class, 'frontAwesome'])->name('elements.font-awesome');
    Route::get('/elements/typography', [ElementController::class, 'typography'])->name('elements.typography');
});
