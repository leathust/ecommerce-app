<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_seeder_creates_varied_customers_and_can_run_repeatedly(): void
    {
        $this->seed(CustomerSeeder::class);

        $demoCustomers = User::where('email', 'like', 'customer%@example.test');
        $this->assertSame(45, (clone $demoCustomers)->count());
        $this->assertSame(35, (clone $demoCustomers)->where('status', 'active')->count());
        $this->assertSame(4, (clone $demoCustomers)->where('status', 'inactive')->count());
        $this->assertSame(6, (clone $demoCustomers)->where('status', 'blocked')->count());

        $first = User::where('email', 'customer001@example.test')->firstOrFail();
        $this->assertSame('demo-customer-001', $first->username);
        $this->assertSame('0900000001', $first->phone);
        $this->assertTrue(Hash::check('Customer@123', $first->password));
        $this->assertTrue($first->isCustomer());

        $originalPassword = $first->password;
        $this->seed(CustomerSeeder::class);

        $this->assertSame(45, User::where('email', 'like', 'customer%@example.test')->count());
        $this->assertSame($originalPassword, $first->fresh()->password);
        $this->assertDatabaseCount('user_roles', 0);
    }
}
