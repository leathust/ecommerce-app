<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemSettingService;
use App\Support\MoneyFormatter;
use App\Support\SystemSettingPresenter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemSettingConsumerTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_uses_localized_branding_contact_and_managed_assets(): void
    {
        App::setLocale('vi');
        $this->settings([
            'site.name_vi' => 'Cửa hàng Việt',
            'site.name_en' => 'English Store',
            'site.tagline_vi' => 'Thiết bị chính hãng',
            'site.logo' => 'system-settings/logo/brand.png',
            'site.favicon' => 'system-settings/favicon/site.ico',
            'contact.email' => 'hotro@example.com',
            'contact.phone' => '0901 234 567',
            'contact.address_vi' => "12 Đường Mẫu\nTP. Hồ Chí Minh",
            'contact.working_hours_vi' => 'Thứ Hai - Thứ Sáu',
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<html lang="vi">', false)
            ->assertSee('Cửa hàng Việt')
            ->assertSee('Thiết bị chính hãng')
            ->assertSee('/storage/system-settings/logo/brand.png', false)
            ->assertSee('/storage/system-settings/favicon/site.ico', false)
            ->assertSee('hotro@example.com')
            ->assertSee('0901 234 567')
            ->assertSee('12 Đường Mẫu');
    }

    public function test_storefront_uses_english_values_and_falls_back_safely(): void
    {
        App::setLocale('en');
        $this->settings([
            'site.name_vi' => 'Tên dự phòng',
            'site.name_en' => 'English Store',
            'contact.address_vi' => 'Địa chỉ dự phòng',
            'contact.address_en' => '',
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('English Store')
            ->assertSee('Địa chỉ dự phòng')
            ->assertDontSee('support@sprylo.example');
    }

    public function test_invoice_uses_seller_settings_money_formatter_and_order_snapshot(): void
    {
        App::setLocale('vi');
        $this->seed(RolePermissionSeeder::class);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());
        $this->settings([
            'business.legal_name' => 'Công ty Thương mại ABC',
            'business.tax_code' => '0312345678',
            'business.invoice_footer_vi' => 'Cảm ơn và hẹn gặp lại.',
            'contact.address_vi' => '99 Nguyễn Huệ',
            'contact.phone' => '028 1234 5678',
            'contact.email' => 'invoice@example.com',
            'commerce.currency_symbol' => 'VNĐ',
        ]);
        $order = Order::create([
            'order_code' => 'SETTING-INVOICE', 'status' => 'delivered', 'payment_method' => 'cod',
            'payment_status' => 'paid', 'subtotal' => 123456, 'shipping_fee' => 10000,
            'total_amount' => 133456, 'recipient_name' => 'Khách snapshot',
            'recipient_phone' => '0900000000', 'shipping_address' => 'Địa chỉ snapshot',
        ]);
        $order->items()->create([
            'product_name' => 'Sản phẩm snapshot', 'sku' => 'SNAP-01', 'quantity' => 1,
            'price' => 123456, 'total_price' => 123456,
        ]);

        $this->actingAs($manager)->get(route('admin.orders.print', $order))
            ->assertOk()
            ->assertSee('Công ty Thương mại ABC')
            ->assertSee('0312345678')
            ->assertSee('99 Nguyễn Huệ')
            ->assertSee('Cảm ơn và hẹn gặp lại.')
            ->assertSee('123.456 VNĐ')
            ->assertSee('133.456 VNĐ')
            ->assertSee('Khách snapshot')
            ->assertSee('Địa chỉ snapshot')
            ->assertSee('Sản phẩm snapshot');
    }

    public function test_setting_content_is_escaped_and_cached_across_composed_views(): void
    {
        $this->settings([
            'site.name_vi' => '<script>alert("brand")</script>',
            'contact.address_vi' => '<img src=x onerror=alert(1)>',
        ]);
        App::setLocale('vi');
        DB::enableQueryLog();

        $response = $this->get(route('contact'));
        $settingsQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'from `settings`'),
        );

        $response->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;brand&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("brand")</script>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertLessThanOrEqual(1, $settingsQueries->count());
    }

    public function test_invoice_uses_english_locale_and_escapes_seller_settings(): void
    {
        App::setLocale('en');
        $this->seed(RolePermissionSeeder::class);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());
        $this->settings([
            'business.legal_name' => '<script>alert("seller")</script>',
            'business.invoice_footer_vi' => 'Chân trang tiếng Việt',
            'business.invoice_footer_en' => 'English invoice footer',
            'contact.address_vi' => 'Địa chỉ tiếng Việt',
            'contact.address_en' => 'English seller address',
        ]);
        $order = Order::create([
            'order_code' => 'SETTING-INVOICE-EN', 'status' => 'pending', 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'subtotal' => 100000, 'shipping_fee' => 0,
            'total_amount' => 100000, 'recipient_name' => 'Snapshot customer',
            'recipient_phone' => '0900000000', 'shipping_address' => 'Snapshot address',
        ]);

        $this->actingAs($manager)->get(route('admin.orders.print', $order))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('English seller address')
            ->assertSee('English invoice footer')
            ->assertDontSee('Chân trang tiếng Việt')
            ->assertDontSee('Địa chỉ tiếng Việt')
            ->assertSee('&lt;script&gt;alert(&quot;seller&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("seller")</script>', false);
    }

    public function test_presenters_and_money_formatter_have_safe_defaults_without_database_values(): void
    {
        $presenter = app(SystemSettingPresenter::class);
        $storefront = $presenter->storefront('unsupported');
        $invoice = $presenter->invoice('unsupported');

        $this->assertSame('vi', $storefront['locale']);
        $this->assertSame((string) config('app.name'), $storefront['name']);
        $this->assertNull($storefront['logo_url']);
        $this->assertNull($storefront['favicon_url']);
        $this->assertSame('vi', $invoice['locale']);
        $this->assertSame((string) config('app.name'), $invoice['legal_name']);
        $this->assertSame('Cảm ơn quý khách đã mua hàng.', $invoice['footer']);
        $this->assertSame('0 ₫', app(MoneyFormatter::class)->format(null));
    }

    public function test_seo_social_defaults_overrides_environment_guard_and_public_boundary(): void
    {
        App::setLocale('vi');
        $this->settings([
            'site.name_vi' => 'Cửa hàng SEO',
            'seo.meta_title_vi' => 'Tiêu đề SEO mặc định',
            'seo.meta_description_vi' => 'Mô tả SEO mặc định.',
            'seo.og_image' => 'system-settings/og-image/share.jpg',
            'seo.indexing_enabled' => '1',
            'social.facebook_url' => 'https://facebook.com/example-store',
            'social.instagram_url' => '',
            'social.youtube_url' => 'https://youtube.com/@example-store',
            'social.tiktok_url' => 'javascript:alert(1)',
            'mail.from_name' => 'Private sender name',
            'mail.contact_recipient' => 'private-recipient@example.com',
        ]);

        $home = $this->get(route('home'));
        $home->assertOk()
            ->assertSee('<title>Tiêu đề SEO mặc định</title>', false)
            ->assertSee('<meta name="description" content="Mô tả SEO mặc định." />', false)
            ->assertSee('<link rel="canonical" href="http://localhost" />', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow" />', false)
            ->assertSee('<meta property="og:image" content="http://localhost/storage/system-settings/og-image/share.jpg" />', false)
            ->assertSee('https://facebook.com/example-store', false)
            ->assertSee('https://youtube.com/@example-store', false)
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('private-recipient@example.com')
            ->assertDontSee('Private sender name');

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<title>Liên hệ | Cửa hàng SEO</title>', false)
            ->assertSee('content="Gửi yêu cầu hỗ trợ và liên hệ với Cửa hàng SEO."', false);

        $storefront = app(SystemSettingPresenter::class)->storefront('vi');
        $this->assertArrayNotHasKey('mail', $storefront);
        $this->assertSame(
            ['facebook', 'youtube'],
            array_keys($storefront['social']),
        );

        $previousEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->get(route('home'))
                ->assertOk()
                ->assertSee('<meta name="robots" content="index,follow" />', false);
        } finally {
            app()->detectEnvironment(fn (): string => $previousEnvironment);
        }
    }

    /** @param array<string, string|null> $values */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->create(['setting_key' => $key, 'setting_value' => $value]);
        }

        app(SystemSettingService::class)->forgetCache();
    }
}
