<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminContactController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ElementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// User routes
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

        Route::get('products', [AdminController::class, 'products'])
            ->middleware('permission:products.view')
            ->name('products');
        Route::get('orders', [AdminController::class, 'orders'])
            ->middleware('permission:orders.view')
            ->name('orders');
        Route::get('customers', [AdminController::class, 'customers'])
            ->middleware('permission:customers.view')
            ->name('customers');
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
