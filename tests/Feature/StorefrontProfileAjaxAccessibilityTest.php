<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StorefrontProfileAjaxAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_account_forms_expose_ajax_hooks_live_region_and_native_fallback_contract(): void
    {
        $this->actingAs($this->customer())
            ->get(route('account'))
            ->assertOk()
            ->assertSee('data-profile-feedback', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-atomic="true"', false)
            ->assertSee('data-profile-form="profile"', false)
            ->assertSee('data-profile-form="password"', false)
            ->assertSee('data-profile-submit', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_profile_json_success_is_server_confirmed_and_minimal_for_ajax(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->patchJson(route('storefront.profile.update'), [
                'first_name' => 'Server',
                'last_name' => 'Confirmed',
                'phone' => '0900000001',
            ])
            ->assertOk()
            ->assertJsonPath('profile.display_name', 'Server Confirmed')
            ->assertJsonPath('profile.phone', '0900000001')
            ->assertJsonMissingPath('profile.email')
            ->assertJsonMissingPath('profile.status')
            ->assertJsonMissingPath('profile.roles');

        $this->assertSame('Server Confirmed', $customer->refresh()->name);
    }

    public function test_password_ajax_success_returns_no_secret_and_keeps_customer_authenticated(): void
    {
        $customer = $this->customer();
        $newPassword = 'Changed!Pass456';

        $response = $this->actingAs($customer)
            ->putJson(route('storefront.password.update'), [
                'current_password' => 'Current!Pass123',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertAuthenticatedAs($customer);
        $this->assertTrue(Hash::check($newPassword, $customer->refresh()->password));
        $this->assertStringNotContainsString('Current!Pass123', $response->getContent());
        $this->assertStringNotContainsString($newPassword, $response->getContent());
    }

    public function test_ajax_validation_is_localized_and_uses_standard_field_error_shape(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->patchJson(route('storefront.profile.update'), [
                'first_name' => str_repeat('a', 101),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_name.0', 'The first name may not be greater than 100 characters.');

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->putJson(route('storefront.password.update'), [
                'current_password' => 'wrong',
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_profile_script_has_pending_stale_focus_and_server_confirmed_guards(): void
    {
        $script = file_get_contents(public_path('assets/frontend/js/main.js'));
        $start = strpos($script, '// Account forms: AJAX enhancement only.');
        $end = strpos($script, '// Newsletter: AJAX enhancement.', $start);
        $profileScript = substr($script, $start, $end - $start);

        $this->assertStringContainsString('new WeakMap()', $profileScript);
        $this->assertStringContainsString('new AbortController()', $profileScript);
        $this->assertStringContainsString("form.setAttribute('aria-busy', 'true')", $profileScript);
        $this->assertStringContainsString("headers: { Accept: 'application/json'", $profileScript);
        $this->assertStringContainsString('new FormData(form)', $profileScript);
        $this->assertStringContainsString('renderProfileErrors', $profileScript);
        $this->assertStringContainsString('firstInvalid?.focus', $profileScript);
        $this->assertStringContainsString('applyConfirmedProfile(payload)', $profileScript);
        $this->assertStringContainsString("form.dataset.profileForm === 'password'", $profileScript);
    }

    public function test_profile_script_never_persists_or_broadcasts_pii_and_secrets(): void
    {
        $script = file_get_contents(public_path('assets/frontend/js/main.js'));
        $start = strpos($script, '// Account forms: AJAX enhancement only.');
        $end = strpos($script, '// Newsletter: AJAX enhancement.', $start);
        $profileScript = substr($script, $start, $end - $start);

        $this->assertStringNotContainsString('localStorage', $profileScript);
        $this->assertStringNotContainsString('sessionStorage', $profileScript);
        $this->assertStringNotContainsString('BroadcastChannel', $profileScript);
        $this->assertStringNotContainsString('postMessage', $profileScript);
        $this->assertStringNotContainsString('history.', $profileScript);
        $this->assertStringNotContainsString('dataset.password', $profileScript);
        $this->assertStringNotContainsString('dataset.currentPassword', $profileScript);
    }

    public function test_profile_css_has_pending_focus_and_reduced_motion_contract(): void
    {
        $css = file_get_contents(public_path('assets/frontend/css/styles.css'));

        $this->assertStringContainsString('.account-feedback--ajax:focus', $css);
        $this->assertStringContainsString('.account-form.is-pending', $css);
        $this->assertStringContainsString('.account-form[aria-busy="true"]', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    private function customer(): User
    {
        return User::factory()->create([
            'password' => Hash::make('Current!Pass123'),
            'status' => 'active',
        ]);
    }
}
