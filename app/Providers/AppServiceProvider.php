<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\HomeBanner;
use App\Services\StorefrontHomeService;
use App\Services\CartIdentityService;
use App\Support\MoneyFormatter;
use App\Support\StorefrontCartContext;
use App\Support\StorefrontMenuPresenter;
use App\Support\StorefrontWishlistContext;
use App\Support\SystemSettingPresenter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Prefer native schema operations even when Doctrine DBAL is installed, so
        // SQLite (used by the test suite) keeps its native drop-column semantics
        // instead of rebuilding tables and recreating their indexes.
        \Illuminate\Database\Schema\Builder::$alwaysUsesNativeSchemaOperationsIfPossible = true;

        $this->app->scoped(CartIdentityService::class);
        $this->app->scoped(StorefrontCartContext::class);
        $this->app->scoped(StorefrontWishlistContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        foreach ([Product::class, ProductImage::class, Category::class, Brand::class, HomeBanner::class] as $model) {
            $model::saved(fn (): mixed => app(StorefrontHomeService::class)->forget());
            $model::deleted(fn (): mixed => app(StorefrontHomeService::class)->forget());
        }

        View::composer('frontend.*', function ($view): void {
            $view->with('storefrontSettings', app(SystemSettingPresenter::class)->storefront(app()->getLocale()));
            $view->with('moneyFormatter', app(MoneyFormatter::class));

            $wishlist = app(StorefrontWishlistContext::class)->data();
            $view->with('storefrontIsCustomer', $wishlist['is_customer']);
            $view->with('storefrontWishlistIds', $wishlist['product_ids']);
            $view->with('storefrontWishlistCount', $wishlist['count']);

            $cart = app(StorefrontCartContext::class)->data();
            $view->with('storefrontCartCount', $cart['count']);
        });

        // Resolve both locations once; all storefront menu partials share this tree.
        View::composer('frontend.layout', function ($view): void {
            $route = request()->route();

            $view->with('storefrontMenus', app(StorefrontMenuPresenter::class)->navigation(
                app()->getLocale(),
                is_object($route) ? $route->getName() : null,
                request()->getPathInfo(),
            ));
        });
    }
}
