<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemSettingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->enableGoogleLogin();
    }

    public function test_redirect_stores_safe_return_to_and_returns_google_url(): void
    {
        $this->mockGoogleRedirect();

        $this->get(route('login.google', ['return_to' => '/shop?page=2']))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');

        $this->assertSame('/shop?page=2', session('google.return_to'));
    }

    public function test_redirect_rejects_unsafe_return_to(): void
    {
        $this->mockGoogleRedirect();

        $this->get(route('login.google', ['return_to' => 'https://evil.example/steal']))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');

        $this->assertNull(session('google.return_to'));
    }

    public function test_callback_creates_new_customer_and_logs_in(): void
    {
        $this->mockGoogleCallback($this->fakeGoogleUser([
            'email' => 'google-new@example.com',
            'name' => 'Google New',
            'raw' => ['given_name' => 'Google', 'family_name' => 'New'],
        ]));

        $this->get(route('login.google.callback'))->assertRedirect(route('home'));

        $user = User::query()->where('email', 'google-new@example.com')->firstOrFail();

        $this->assertNull($user->password);
        $this->assertSame('Google New', $user->name);
        $this->assertSame('Google', $user->first_name);
        $this->assertSame('New', $user->last_name);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', (string) $user->username);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_logs_in_existing_customer_by_email_without_creating_duplicate(): void
    {
        $customer = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);

        $this->mockGoogleCallback($this->fakeGoogleUser(['email' => 'existing@example.com']));

        $this->get(route('login.google.callback'))->assertRedirect(route('home'));

        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
        $this->assertAuthenticatedAs($customer);
    }

    public function test_callback_rejects_admin_email(): void
    {
        $admin = User::factory()->create([
            'email' => 'google-admin@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        $this->mockGoogleCallback($this->fakeGoogleUser(['email' => 'google-admin@example.com']));

        $this->get(route('login.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_rejects_blocked_customer(): void
    {
        User::factory()->create([
            'email' => 'google-blocked@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'blocked',
        ]);

        $this->mockGoogleCallback($this->fakeGoogleUser(['email' => 'google-blocked@example.com']));

        $this->get(route('login.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_page_renders_google_link_and_preserves_return_to(): void
    {
        $this->get(route('login', ['return_to' => '/shop']))
            ->assertOk()
            ->assertSee(route('login.google', ['return_to' => '/shop']), false);
    }

    public function test_google_routes_are_get_routes_within_storefront_locale_group(): void
    {
        $redirect = Route::getRoutes()->getByName('login.google');
        $callback = Route::getRoutes()->getByName('login.google.callback');

        $this->assertSame(['GET', 'HEAD'], $redirect->methods());
        $this->assertSame(['GET', 'HEAD'], $callback->methods());
        $this->assertContains('web', $redirect->gatherMiddleware());
        $this->assertContains('storefront.locale', $redirect->gatherMiddleware());
        $this->assertContains('storefront.locale', $callback->gatherMiddleware());
    }

    public function test_google_login_is_hidden_and_routes_are_unavailable_when_disabled(): void
    {
        $this->writeSettings(['auth.google_login_enabled' => '0']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('login.google'), false);

        $this->get(route('login.google'))->assertNotFound();
        $this->get(route('login.google.callback'))->assertNotFound();
    }

    public function test_google_login_is_hidden_when_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);
        $this->writeSettings([
            'auth.google_login_enabled' => '1',
            'auth.google_client_id' => '',
            'auth.google_client_secret' => '',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('login.google'), false);

        $this->get(route('login.google'))->assertNotFound();
    }

    private function enableGoogleLogin(): void
    {
        $this->writeSettings([
            'auth.google_login_enabled' => '1',
            'auth.google_client_id' => 'test-client-id',
            'auth.google_client_secret' => 'test-client-secret',
            'auth.google_redirect_uri' => 'http://localhost/auth/google/callback',
        ]);
    }

    /** @param array<string, string> $values */
    private function writeSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_by' => null],
            );
        }

        app(SystemSettingService::class)->forgetCache();
    }

    private function fakeGoogleUser(array $attributes = []): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getEmail')->andReturn($attributes['email'] ?? 'google@example.com');
        $user->shouldReceive('getName')->andReturn($attributes['name'] ?? 'Google User');
        $user->shouldReceive('getRaw')->andReturn($attributes['raw'] ?? []);

        return $user;
    }

    private function mockGoogleRedirect(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')->andReturn(redirect()->away('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    private function mockGoogleCallback(SocialiteUser $googleUser): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }
}
