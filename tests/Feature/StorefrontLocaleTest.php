<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SystemSettingService;
use App\Support\StorefrontLocaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorefrontLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.locale', 'vi');
        app()->setLocale('vi');

        Route::middleware(['web', 'storefront.locale'])
            ->get('/_test/storefront-locale', fn (): string => app()->getLocale());
        Route::middleware('web')
            ->get('/admin/_test/application-locale', fn (): string => app()->getLocale());
    }

    public function test_valid_session_locale_has_highest_priority_and_is_normalized(): void
    {
        $this->putSetting('site.default_locale', 'vi');

        $this->withSession([StorefrontLocaleResolver::SESSION_KEY => ' EN '])
            ->get('/_test/storefront-locale')
            ->assertOk()
            ->assertSeeText('en');
    }

    public function test_invalid_session_locale_falls_back_to_system_setting(): void
    {
        $this->putSetting('site.default_locale', 'en');

        $this->withSession([StorefrontLocaleResolver::SESSION_KEY => 'fr'])
            ->get('/_test/storefront-locale')
            ->assertOk()
            ->assertSeeText('en');
    }

    public function test_config_and_vi_are_safe_fallbacks_when_setting_definition_is_unavailable(): void
    {
        $definitions = config('system_settings.settings');
        unset($definitions['site.default_locale']);
        config()->set('system_settings.settings', $definitions);
        config()->set('app.locale', 'en');

        $this->get('/_test/storefront-locale')
            ->assertOk()
            ->assertSeeText('en');

        config()->set('app.locale', 'unsupported');

        $this->get('/_test/storefront-locale')
            ->assertOk()
            ->assertSeeText('vi');
    }

    public function test_storefront_views_receive_locale_before_view_composers_run(): void
    {
        $this->putSetting('site.default_locale', 'en');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="en">', false);
    }

    public function test_storefront_session_locale_does_not_leak_into_admin_requests(): void
    {
        $this->withSession([StorefrontLocaleResolver::SESSION_KEY => 'en'])
            ->get('/_test/storefront-locale')
            ->assertOk()
            ->assertSeeText('en');

        $this->withSession([StorefrontLocaleResolver::SESSION_KEY => 'en'])
            ->get('/admin/_test/application-locale')
            ->assertOk()
            ->assertSeeText('vi');
    }

    public function test_language_endpoint_persists_locale_and_keeps_internal_query_string(): void
    {
        $this->post(route('language.update'), [
            'locale' => 'en',
            'return_url' => '/shop?sort=newest&page=2',
        ])->assertRedirect('/shop?sort=newest&page=2')
            ->assertSessionHas(StorefrontLocaleResolver::SESSION_KEY, 'en');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="en">', false);
    }

    public function test_language_endpoint_rejects_unsupported_locale(): void
    {
        $this->from(route('home'))->post(route('language.update'), [
            'locale' => 'fr',
            'return_url' => '/shop',
        ])->assertRedirect(route('home'))
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing(StorefrontLocaleResolver::SESSION_KEY);

        $this->postJson(route('language.update'), ['locale' => 'javascript'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
    }

    public function test_language_endpoint_accepts_absolute_same_origin_url(): void
    {
        $returnUrl = route('shop').'?category=audio';

        $this->post(route('language.update'), [
            'locale' => 'en',
            'return_url' => $returnUrl,
        ])->assertRedirect($returnUrl)
            ->assertSessionHas(StorefrontLocaleResolver::SESSION_KEY, 'en');
    }

    /** @dataProvider unsafeReturnUrlProvider */
    public function test_language_endpoint_blocks_unsafe_return_urls(string $returnUrl): void
    {
        $this->from('/cart')->post(route('language.update'), [
            'locale' => 'en',
            'return_url' => $returnUrl,
        ])->assertRedirect('/cart')
            ->assertSessionHas(StorefrontLocaleResolver::SESSION_KEY, 'en');
    }

    /** @return array<string, array{string}> */
    public static function unsafeReturnUrlProvider(): array
    {
        return [
            'external host' => ['https://example.com/phishing'],
            'protocol relative' => ['//example.com/phishing'],
            'foreign scheme' => ['javascript:alert(1)'],
            'admin path' => ['/admin/users'],
            'encoded admin path' => ['/%61dmin/settings'],
            'backslash ambiguity' => ['/\\example.com'],
        ];
    }

    public function test_legacy_get_redirects_home_without_mutating_locale_session(): void
    {
        $this->withSession([StorefrontLocaleResolver::SESSION_KEY => 'en'])
            ->get(route('language'))
            ->assertRedirect(route('home'))
            ->assertSessionHas(StorefrontLocaleResolver::SESSION_KEY, 'en');
    }

    public function test_language_route_uses_web_locale_and_throttle_middleware(): void
    {
        $route = Route::getRoutes()->getByName('language.update');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('storefront.locale', $route->gatherMiddleware());
        $this->assertContains('throttle:20,1', $route->gatherMiddleware());
    }

    public function test_language_switcher_renders_desktop_mobile_accessibility_and_no_js_forms(): void
    {
        $response = $this->withSession([StorefrontLocaleResolver::SESSION_KEY => 'vi'])
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('language-switcher--desktop', false)
            ->assertSee('language-switcher--mobile', false)
            ->assertSee('data-language-trigger', false)
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('role="menu"', false)
            ->assertSee('aria-current="true" disabled', false)
            ->assertSee('<noscript>', false)
            ->assertSee(route('language.update'), false)
            ->assertSee('name="return_url" value="'.route('home').'"', false)
            ->assertSee('Tiếng Việt')
            ->assertSee('English');

        $this->assertSame(6, substr_count($response->getContent(), 'name="locale"'));
        $this->assertSame(4, substr_count($response->getContent(), 'type="hidden" name="locale"'));
        $this->assertSame(2, substr_count($response->getContent(), 'name="locale" value="en"'));
    }

    public function test_language_switcher_marks_english_as_current_after_switch(): void
    {
        $this->post(route('language.update'), [
            'locale' => 'en',
            'return_url' => route('home'),
        ])->assertRedirect(route('home'));

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('language-menu-desktop', false)
            ->assertSee('language-menu-mobile', false);

        $this->assertSame(2, substr_count($response->getContent(), 'value="en">'));
        $this->assertSame(2, substr_count($response->getContent(), 'value="en" selected'));
    }

    private function putSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value],
        );
        app(SystemSettingService::class)->forgetCache();
    }
}
