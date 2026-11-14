<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorefrontProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_can_update_normalized_profile_with_html_fallback(): void
    {
        $customer = User::factory()->create([
            'name' => 'Old Name',
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->patch(route('storefront.profile.update'), [
                'first_name' => "  An\t Minh  ",
                'last_name' => '  Nguyễn   Văn ',
                'phone' => '  +84  900  000  000 ',
            ])
            ->assertRedirect(route('account'))
            ->assertSessionHas('profile_success', 'Thông tin cá nhân đã được cập nhật.');

        $customer->refresh();
        $this->assertSame('An Minh', $customer->first_name);
        $this->assertSame('Nguyễn Văn', $customer->last_name);
        $this->assertSame('+84 900 000 000', $customer->phone);
        $this->assertSame('An Minh Nguyễn Văn', $customer->name);
    }

    public function test_empty_optional_fields_become_null_and_preserve_existing_fallback_name(): void
    {
        $customer = User::factory()->create([
            'name' => 'Fallback Name',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'phone' => '0900000000',
        ]);

        $this->actingAs($customer)->patch(route('storefront.profile.update'), [
            'first_name' => '   ',
            'last_name' => '',
            'phone' => " \t ",
        ])->assertRedirect(route('account'));

        $customer->refresh();
        $this->assertNull($customer->first_name);
        $this->assertNull($customer->last_name);
        $this->assertNull($customer->phone);
        $this->assertSame('Fallback Name', $customer->name);
    }

    public function test_json_response_contains_only_public_server_confirmed_profile_fields(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->patchJson(route('storefront.profile.update'), [
                'first_name' => 'Linh',
                'last_name' => 'Tran',
                'phone' => '0909000000',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Thông tin cá nhân đã được cập nhật.')
            ->assertJsonPath('profile.display_name', 'Linh Tran')
            ->assertJsonPath('profile.first_name', 'Linh')
            ->assertJsonPath('profile.last_name', 'Tran')
            ->assertJsonPath('profile.phone', '0909000000')
            ->assertJsonMissingPath('profile.email')
            ->assertJsonMissingPath('profile.username')
            ->assertJsonMissingPath('profile.status')
            ->assertJsonMissingPath('profile.roles')
            ->assertJsonMissingPath('profile.permissions');
    }

    public function test_over_posted_security_fields_are_ignored_and_another_customer_is_untouched(): void
    {
        $customer = User::factory()->create([
            'email' => 'owner@example.test',
            'username' => 'owner',
            'status' => 'active',
        ]);
        $other = User::factory()->create([
            'first_name' => 'Other',
            'phone' => '0111111111',
        ]);

        $this->actingAs($customer)->patchJson(route('storefront.profile.update'), [
            'user_id' => $other->id,
            'first_name' => 'Safe',
            'last_name' => 'Owner',
            'phone' => '0999999999',
            'email' => 'attacker@example.test',
            'username' => 'attacker',
            'status' => 'blocked',
            'roles' => [1],
            'permissions' => [1],
            'must_change_password' => true,
            'last_login_at' => now()->toIso8601String(),
        ])->assertOk();

        $customer->refresh();
        $other->refresh();
        $this->assertSame('Safe', $customer->first_name);
        $this->assertSame('owner@example.test', $customer->email);
        $this->assertSame('owner', $customer->username);
        $this->assertSame('active', $customer->status);
        $this->assertFalse($customer->must_change_password);
        $this->assertSame('Other', $other->first_name);
        $this->assertSame('0111111111', $other->phone);
    }

    public function test_validation_uses_profile_error_bag_for_html_and_localized_json(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->from(route('account'))
            ->patch(route('storefront.profile.update'), [
                'first_name' => str_repeat('a', 101),
                'phone' => str_repeat('1', 31),
            ])
            ->assertRedirect(route('account'))
            ->assertSessionHasErrors(['first_name', 'phone'], null, 'profile')
            ->assertSessionHasInput('first_name');

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->patchJson(route('storefront.profile.update'), [
                'last_name' => str_repeat('b', 101),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('last_name')
            ->assertJsonPath('errors.last_name.0', 'The last name may not be greater than 100 characters.');
    }

    public function test_guest_admin_and_inactive_customer_cannot_update_profile(): void
    {
        $payload = ['first_name' => 'Blocked'];
        $this->patch(route('storefront.profile.update'), $payload)->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->patch(route('storefront.profile.update'), $payload)->assertForbidden();
        $this->assertNotSame('Blocked', $admin->refresh()->first_name);

        auth()->logout();
        $inactive = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($inactive)
            ->patch(route('storefront.profile.update'), $payload)
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame('Blocked', $inactive->refresh()->first_name);
    }

    public function test_profile_route_keeps_web_csrf_and_named_throttle_boundary(): void
    {
        $route = Route::getRoutes()->getByName('storefront.profile.update');

        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('storefront.locale', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('customer', $route->gatherMiddleware());
        $this->assertContains('throttle:profile-mutations', $route->gatherMiddleware());
        $this->assertSame(['PATCH'], $route->methods());
    }

    public function test_profile_mutations_are_rate_limited_per_customer(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->patchJson(route('storefront.profile.update'), [
                'first_name' => 'Attempt '.$attempt,
            ])->assertOk();
        }

        $this->patchJson(route('storefront.profile.update'), [
            'first_name' => 'Throttled',
        ])->assertTooManyRequests();
    }
}
