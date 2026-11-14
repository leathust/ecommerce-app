<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StorefrontProfileViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_vietnamese_account_page_renders_overview_forms_shortcuts_and_logout(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Tổng quan tài khoản')
            ->assertSee('Thông tin cá nhân')
            ->assertSee('Đổi mật khẩu')
            ->assertSee('Email và tên đăng nhập là thông tin định danh')
            ->assertSee(route('wishlist.index'), false)
            ->assertSee(route('cart'), false)
            ->assertSee(route('logout'), false)
            ->assertSee(route('storefront.profile.update'), false)
            ->assertSee(route('storefront.password.update'), false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('autocomplete="given-name"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('noindex,nofollow', false);
    }

    public function test_english_account_page_translates_profile_and_security_content(): void
    {
        $this->actingAs($this->customer())
            ->withSession(['storefront_locale' => 'en'])
            ->get(route('account'))
            ->assertOk()
            ->assertSee('Account overview')
            ->assertSee('Personal information')
            ->assertSee('Change password')
            ->assertSee('Email and username identify your account')
            ->assertSee('Sign-in session')
            ->assertSee('Save information')
            ->assertDontSee('Thông tin cá nhân');
    }

    public function test_profile_values_are_escaped_and_password_inputs_never_have_values(): void
    {
        $customer = $this->customer([
            'name' => '<script>alert(1)</script>',
            'first_name' => '"><img src=x onerror=alert(1)>',
            'last_name' => '<b>Unsafe</b>',
            'phone' => '"><svg/onload=alert(1)>',
        ]);

        $response = $this->actingAs($customer)->get(route('account'))->assertOk();

        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('&lt;b&gt;Unsafe&lt;/b&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertDontSee('<svg/onload=alert(1)>', false)
            ->assertDontSee('name="current_password" value=', false)
            ->assertDontSee('name="password" value=', false)
            ->assertDontSee('name="password_confirmation" value=', false);
    }

    public function test_profile_validation_preserves_safe_old_input_and_uses_profile_error_markup(): void
    {
        $customer = $this->customer();
        $invalidName = str_repeat('a', 101);

        $this->actingAs($customer)
            ->from(route('account'))
            ->patch(route('storefront.profile.update'), [
                'first_name' => $invalidName,
                'last_name' => 'Safe old value',
            ])
            ->assertRedirect(route('account'));

        $this->get(route('account'))
            ->assertOk()
            ->assertSee($invalidName, false)
            ->assertSee('Safe old value', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('profile-first-name-error', false)
            ->assertSee('role="alert"', false);
    }

    public function test_password_validation_uses_separate_error_markup_without_echoing_secrets(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->from(route('account'))
            ->put(route('storefront.password.update'), [
                'current_password' => 'wrong-secret',
                'password' => 'weak-secret',
                'password_confirmation' => 'other-secret',
            ])
            ->assertRedirect(route('account'));

        $response = $this->get(route('account'))->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('profile-current-password-error', $content);
        $this->assertStringContainsString('profile-new-password-error', $content);
        $this->assertStringNotContainsString('wrong-secret', $content);
        $this->assertStringNotContainsString('weak-secret', $content);
        $this->assertStringNotContainsString('other-secret', $content);
    }

    public function test_account_styles_define_desktop_tablet_mobile_and_accessible_field_states(): void
    {
        $css = file_get_contents(public_path('assets/frontend/css/styles.css'));

        $this->assertStringContainsString('.account-layout { display: grid;', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(250px, 0.72fr) minmax(0, 1.7fr)', $css);
        $this->assertStringContainsString('@media (max-width: 960px)', $css);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
        $this->assertStringContainsString('.account-form__submit { width: 100%; }', $css);
        $this->assertStringContainsString('.field input[aria-invalid="true"]', $css);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Nguyen An',
            'first_name' => 'An',
            'last_name' => 'Nguyen',
            'phone' => '0900000000',
            'password' => Hash::make('Current!Pass123'),
            'status' => 'active',
        ], $attributes));
    }
}
