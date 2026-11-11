<?php

namespace App\Providers;

use App\Support\MoneyFormatter;
use App\Support\StorefrontMenuPresenter;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('frontend.*', function ($view): void {
            $view->with('storefrontSettings', app(SystemSettingPresenter::class)->storefront(app()->getLocale()));
            $view->with('moneyFormatter', app(MoneyFormatter::class));
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
