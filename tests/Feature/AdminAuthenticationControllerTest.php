<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminAuthController;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        RateLimiter::clear('admin@example.com|127.0.0.1');

        Route::middleware('web')->group(function (): void {
            Route::get('/test/admin-login', [AdminAuthController::class, 'showLoginForm']);
            Route::post('/test/admin-login', [AdminAuthController::class, 'login']);
            Route::post('/test/admin-logout', [AdminAuthController::class, 'logout']);
        });
    }

    public function test_login_form_is_rendered(): void
    {
        $this->get('/test/admin-login')
            ->assertOk()
            ->assertViewIs('pages.login.login');
    }

    public function test_active_administrator_can_log_in(): void
    {
        $user = $this->adminUser();

        $response = $this->post('/test/admin-login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->adminUser();

        $this->post('/test/admin-login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_blocked_administrator_is_rejected(): void
    {
        $user = $this->adminUser(['status' => 'blocked']);

        $this->post('/test/admin-login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_without_admin_role_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post('/test/admin-login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        $user = $this->adminUser();

        for ($attempt = 0; $attempt < config('admin.login.max_attempts'); $attempt++) {
            $this->post('/test/admin-login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/test/admin-login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post('/test/admin-logout')
            ->assertRedirect(url('/admin'));

        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function adminUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ], $attributes));

        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());

        return $user;
    }
}
