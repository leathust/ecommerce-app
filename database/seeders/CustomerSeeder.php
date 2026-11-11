<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    private const CUSTOMER_COUNT = 45;

    public function run(): void
    {
        $firstNames = ['An', 'Bình', 'Chi', 'Dũng', 'Giang', 'Hà', 'Hùng', 'Lan', 'Linh', 'Minh', 'Nam', 'Phương', 'Quân', 'Trang', 'Tuấn'];
        $lastNames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Võ', 'Đặng', 'Bùi', 'Đỗ'];

        DB::transaction(function () use ($firstNames, $lastNames): void {
            for ($number = 1; $number <= self::CUSTOMER_COUNT; $number++) {
                $sequence = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $firstName = $firstNames[($number - 1) % count($firstNames)];
                $lastName = $lastNames[($number - 1) % count($lastNames)];
                $status = match (true) {
                    $number % 10 === 0 => 'inactive',
                    $number % 7 === 0 => 'blocked',
                    default => 'active',
                };

                $customer = User::firstOrNew(['email' => 'customer'.$sequence.'@example.test']);
                $customer->fill([
                    'name' => $lastName.' '.$firstName,
                    'username' => 'demo-customer-'.$sequence,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => '090'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
                    'status' => $status,
                ]);

                if (! $customer->exists) {
                    $customer->password = Hash::make('Customer@123');
                    $customer->email_verified_at = $number % 5 === 0 ? null : now();
                }

                $customer->save();
            }
        });
    }
}
