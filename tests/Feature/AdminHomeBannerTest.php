<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\HomeBanner;
use App\Models\Role;
use App\Models\User;
use App\Services\StorefrontHomeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminHomeBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        app(StorefrontHomeService::class)->forget();
    }

    public function test_routes_navigation_and_permissions_follow_banner_rbac(): void
    {
        $expected = [
            'admin.home-banners.index' => 'permission:banners.view',
            'admin.home-banners.create' => 'permission:banners.create',
            'admin.home-banners.store' => 'permission:banners.create',
            'admin.home-banners.edit' => 'permission:banners.update',
            'admin.home-banners.update' => 'permission:banners.update',
            'admin.home-banners.destroy' => 'permission:banners.delete',
        ];
        foreach ($expected as $route => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($route)->gatherMiddleware());
        }

        $manager = $this->userWithRole('catalog_manager');
        $support = $this->userWithRole('customer_support');
        $this->actingAs($manager)->get(route('admin.dashboard'))->assertSee(route('admin.home-banners.index'), false);
        $this->actingAs($support)->get(route('admin.home-banners.index'))->assertForbidden();
    }

    public function test_admin_can_create_update_delete_and_audit_banner(): void
    {
        $actor = $this->userWithRole('catalog_manager');
        $this->actingAs($actor)->post(route('admin.home-banners.store'), $this->payload())
            ->assertRedirect(route('admin.home-banners.index'));
        $banner = HomeBanner::firstOrFail();
        $this->assertSame($actor->id, $banner->created_by);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'home_banner.created', 'subject_id' => $banner->id]);

        $this->actingAs($actor)->put(route('admin.home-banners.update', $banner), $this->payload(['title_vi' => 'Banner cập nhật']))
            ->assertRedirect(route('admin.home-banners.index'));
        $this->assertSame('Banner cập nhật', $banner->fresh()->title_vi);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'home_banner.updated', 'subject_id' => $banner->id]);

        $this->actingAs($actor)->delete(route('admin.home-banners.destroy', $banner))
            ->assertRedirect(route('admin.home-banners.index'));
        $this->assertDatabaseMissing('home_banners', ['id' => $banner->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'home_banner.deleted', 'subject_id' => $banner->id]);
    }

    public function test_validation_rejects_unsafe_links_invalid_schedule_and_unknown_values(): void
    {
        $actor = $this->userWithRole('catalog_manager');

        $this->actingAs($actor)->post(route('admin.home-banners.store'), $this->payload([
            'placement' => 'sidebar',
            'theme' => 'script',
            'link_url' => 'https://evil.example/phish',
            'starts_at' => '2026-12-10 10:00:00',
            'ends_at' => '2026-12-09 10:00:00',
        ]))->assertSessionHasErrors(['placement', 'theme', 'link_url', 'ends_at']);

        $this->assertDatabaseCount('home_banners', 0);
    }

    public function test_published_scope_honors_status_and_schedule_boundaries(): void
    {
        $this->banner(['title_vi' => 'Đang chạy', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addMinute()]);
        $this->banner(['title_vi' => 'Tương lai', 'starts_at' => now()->addMinute()]);
        $this->banner(['title_vi' => 'Hết hạn', 'ends_at' => now()]);
        $this->banner(['title_vi' => 'Đã tắt', 'status' => false]);

        $this->assertSame(['Đang chạy'], HomeBanner::published(now())->pluck('title_vi')->all());
    }

    public function test_storefront_renders_localized_published_banners_and_falls_back_safely(): void
    {
        $this->banner([
            'placement' => 'hero', 'title_vi' => '<script>Hero Việt</script>', 'title_en' => 'English Hero',
            'eyebrow_vi' => 'Nhãn Việt', 'eyebrow_en' => 'English label', 'button_vi' => 'Mua ngay', 'button_en' => 'Shop now',
            'link_url' => 'shop?category=dien-thoai', 'image_url' => 'https://cdn.example.com/hero.webp',
        ]);
        $this->banner([
            'placement' => 'discount', 'title_vi' => 'Khuyến mãi Việt', 'title_en' => '',
            'link_url' => 'products/demo-product', 'theme' => 'watch',
        ]);

        $this->get(route('home'))->assertOk()
            ->assertSee('&lt;script&gt;Hero Việt&lt;/script&gt;', false)
            ->assertSee('https://cdn.example.com/hero.webp', false)
            ->assertSee(url('/shop?category=dien-thoai'), false)
            ->assertSeeText('Khuyến mãi Việt');

        app(StorefrontHomeService::class)->forget();
        $this->withSession(['storefront_locale' => 'en'])->get(route('home'))->assertOk()
            ->assertSeeText('English Hero')
            ->assertSeeText('Khuyến mãi Việt');
    }

    public function test_inactive_or_future_banner_is_not_rendered_and_cache_is_invalidated(): void
    {
        $banner = $this->banner(['title_vi' => 'Ban đầu', 'status' => false]);
        $this->get(route('home'))->assertDontSeeText('Ban đầu');

        $banner->update(['status' => true, 'title_vi' => 'Đã xuất bản']);
        $this->get(route('home'))->assertSeeText('Đã xuất bản');

        $banner->update(['starts_at' => now()->addDay(), 'title_vi' => 'Chưa tới lịch']);
        $this->get(route('home'))->assertDontSeeText('Chưa tới lịch');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'placement' => 'hero', 'title_vi' => 'Banner Việt', 'title_en' => 'English Banner',
            'eyebrow_vi' => 'Nhãn', 'eyebrow_en' => 'Label', 'description_vi' => 'Mô tả', 'description_en' => 'Description',
            'button_vi' => 'Xem ngay', 'button_en' => 'Explore', 'image_url' => 'https://cdn.example.com/banner.webp',
            'link_url' => 'shop', 'theme' => 'purple', 'sort_order' => 10, 'status' => '1',
            'starts_at' => null, 'ends_at' => null,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function banner(array $overrides = []): HomeBanner
    {
        return HomeBanner::create(array_merge($this->payload(), ['status' => true], $overrides));
    }
}
