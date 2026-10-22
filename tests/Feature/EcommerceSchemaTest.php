<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EcommerceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecommerce_schema_and_user_factory_are_valid(): void
    {
        $tables = [
            'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
            'categories', 'brands', 'products', 'product_images', 'carts',
            'cart_items', 'wishlists', 'orders', 'order_items', 'contacts',
            'faqs', 'menus', 'settings',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'username', 'email', 'password', 'first_name', 'last_name',
            'phone', 'status', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumn('contacts', 'order_code'));

        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => $user->username,
            'status' => 'active',
        ]);
    }
}
