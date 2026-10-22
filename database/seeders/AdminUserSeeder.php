<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Create or update the initial super administrator account.
     */
    public function run(): void
    {
        $password = config('admin.password');

        if (blank($password)) {
            throw new RuntimeException('ADMIN_PASSWORD must be configured before seeding the super admin.');
        }

        DB::transaction(function () use ($password): void {
            $role = Role::where('name', 'super_admin')->firstOrFail();

            $user = User::updateOrCreate(
                ['email' => config('admin.email')],
                [
                    'name' => config('admin.name'),
                    'username' => config('admin.username'),
                    'password' => Hash::make($password),
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }
}
