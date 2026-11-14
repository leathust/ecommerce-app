<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StorefrontPasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'Current!Pass123';

    private const NEW_PASSWORD = 'New!SecurePass456';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_can_change_password_and_remains_authenticated_with_regenerated_session(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->withSession(['sentinel' => 'kept']);
        $sessionId = session()->getId();
        $csrfToken = session()->token();

        $this->put(route('storefront.password.update'), $this->validPayload())
            ->assertRedirect(route('account'))
            ->assertSessionHas('password_success', 'Mật khẩu của bạn đã được thay đổi.');

        $customer->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $customer->password));
        $this->assertFalse(Hash::check(self::CURRENT_PASSWORD, $customer->password));
        $this->assertAuthenticatedAs($customer);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($csrfToken, session()->token());
        $this->assertSame('kept', session('sentinel'));
    }

    public function test_json_success_returns_only_localized_message_and_never_credentials(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->putJson(route('storefront.password.update'), $this->validPayload())
            ->assertOk()
            ->assertExactJson(['message' => 'Your password has been changed.']);

        $content = $response->getContent();
        $this->assertStringNotContainsString(self::CURRENT_PASSWORD, $content);
        $this->assertStringNotContainsString(self::NEW_PASSWORD, $content);
        $this->assertAuthenticatedAs($customer);
    }

    public function test_wrong_current_password_is_rejected_without_changing_hash(): void
    {
        $customer = $this->customer();
        $hash = $customer->password;

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->putJson(route('storefront.password.update'), array_merge($this->validPayload(), [
                'current_password' => 'Wrong!Pass1234',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Mật khẩu hiện tại không chính xác.');

        $this->assertSame($hash, $customer->refresh()->password);
    }

    public function test_confirmation_strength_and_password_reuse_are_rejected(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->withSession(['storefront_locale' => 'en']);

        $this->putJson(route('storefront.password.update'), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonFragment(['The new password confirmation does not match.']);

        $this->putJson(route('storefront.password.update'), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::CURRENT_PASSWORD,
            'password_confirmation' => self::CURRENT_PASSWORD,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The new password must be different from your current password.');

        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $customer->refresh()->password));
    }

    public function test_html_validation_uses_password_error_bag_and_never_flashes_secrets(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->from(route('account'))
            ->put(route('storefront.password.update'), [
                'current_password' => 'wrong-secret',
                'password' => 'short-secret',
                'password_confirmation' => 'other-secret',
            ])
            ->assertRedirect(route('account'))
            ->assertSessionHasErrors(['current_password', 'password'], null, 'password');

        $oldInput = session()->getOldInput();
        $this->assertArrayNotHasKey('current_password', $oldInput);
        $this->assertArrayNotHasKey('password', $oldInput);
        $this->assertArrayNotHasKey('password_confirmation', $oldInput);
        $this->assertStringNotContainsString('wrong-secret', serialize(session()->all()));
        $this->assertStringNotContainsString('short-secret', serialize(session()->all()));
    }

    public function test_guest_admin_and_inactive_customer_cannot_change_password(): void
    {
        $this->put(route('storefront.password.update'), $this->validPayload())
            ->assertRedirect(route('login'));

        $admin = $this->customer();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());
        $adminHash = $admin->password;
        $this->actingAs($admin)
            ->put(route('storefront.password.update'), $this->validPayload())
            ->assertForbidden();
        $this->assertSame($adminHash, $admin->refresh()->password);

        auth()->logout();
        $inactive = $this->customer(['status' => 'inactive']);
        $inactiveHash = $inactive->password;
        $this->actingAs($inactive)
            ->put(route('storefront.password.update'), $this->validPayload())
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame($inactiveHash, $inactive->refresh()->password);
    }

    public function test_password_mutations_are_rate_limited_per_customer(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $response = $this->putJson(route('storefront.password.update'), $this->validPayload());
            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->putJson(route('storefront.password.update'), $this->validPayload())
            ->assertTooManyRequests();
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make(self::CURRENT_PASSWORD),
            'status' => 'active',
        ], $attributes));
    }

    /** @return array{current_password: string, password: string, password_confirmation: string} */
    private function validPayload(): array
    {
        return [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }
}
