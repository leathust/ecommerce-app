<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    private const ORDER_COUNT = 48;

    public function run(): void
    {
        if (! User::where('email', 'like', 'customer%@example.test')->exists()) {
            $this->call(CustomerSeeder::class);
        }
        if (! Product::where('sku', 'like', 'DEMO-%')->exists()) {
            $this->call(ProductSeeder::class);
        }

        $customers = User::where('email', 'like', 'customer%@example.test')->orderBy('id')->get();
        $products = Product::where('sku', 'like', 'DEMO-%')->orderBy('id')->get();
        $actorId = User::query()->role(Role::ADMIN_ROLES)->value('id');
        $statuses = array_keys(Order::STATUSES);
        $paymentMethods = array_keys(Order::PAYMENT_METHODS);

        DB::transaction(function () use ($customers, $products, $actorId, $statuses, $paymentMethods): void {
            for ($number = 1; $number <= self::ORDER_COUNT; $number++) {
                $sequence = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $status = $statuses[($number - 1) % count($statuses)];
                $createdAt = now()->subDays(self::ORDER_COUNT - $number)->setTime(8 + ($number % 9), ($number * 7) % 60);
                $customer = $number % 4 === 0 ? null : $customers[($number - 1) % $customers->count()];
                $itemCount = 1 + ($number % 4);
                $itemRows = [];
                $subtotal = 0;

                for ($itemNumber = 0; $itemNumber < $itemCount; $itemNumber++) {
                    $product = $products[(($number - 1) * 3 + $itemNumber) % $products->count()];
                    $quantity = 1 + (($number + $itemNumber) % 3);
                    $price = (int) $product->price;
                    $total = $price * $quantity;
                    $subtotal += $total;
                    $itemRows[] = compact('product', 'quantity', 'price', 'total');
                }

                $shippingFee = $subtotal >= 3000000 ? 0 : 30000;
                $timeline = $this->timeline($status, $createdAt, $number);
                $paymentStatus = match ($status) {
                    'delivered' => 'paid',
                    'cancelled' => $number % 2 === 0 ? 'refunded' : 'failed',
                    default => $number % 5 === 0 ? 'paid' : 'unpaid',
                };

                $order = Order::updateOrCreate(
                    ['order_code' => 'DEMO-ORDER-'.$sequence],
                    [
                        'user_id' => $customer?->id,
                        'status' => $status,
                        'payment_method' => $paymentMethods[($number - 1) % count($paymentMethods)],
                        'payment_status' => $paymentStatus,
                        'subtotal' => $subtotal,
                        'shipping_fee' => $shippingFee,
                        'total_amount' => $subtotal + $shippingFee,
                        'recipient_name' => $customer?->display_name ?? 'Khách vãng lai '.$sequence,
                        'recipient_phone' => $customer?->phone ?? '098'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
                        'shipping_address' => ($number + 10).' Đường Mẫu, Phường '.(($number % 12) + 1).', TP. Hồ Chí Minh',
                        'note' => $number % 6 === 0 ? 'Vui lòng liên hệ trước khi giao.' : null,
                        'cancel_reason' => $status === 'cancelled' ? 'Đơn hàng mẫu được hủy để kiểm thử quy trình.' : null,
                        'confirmed_at' => $timeline['confirmed_at'],
                        'processing_at' => $timeline['processing_at'],
                        'shipped_at' => $timeline['shipped_at'],
                        'delivered_at' => $timeline['delivered_at'],
                        'cancelled_at' => $timeline['cancelled_at'],
                        'stock_deducted_at' => null,
                        'stock_restored_at' => null,
                    ],
                );
                $lastHistory = $timeline['histories'][array_key_last($timeline['histories'])];
                $order->forceFill(['created_at' => $createdAt, 'updated_at' => $lastHistory['created_at']])->saveQuietly();

                $order->items()->delete();
                foreach ($itemRows as $row) {
                    $order->items()->create([
                        'product_id' => $row['product']->id,
                        'product_name' => $row['product']->name_vi,
                        'sku' => $row['product']->sku,
                        'quantity' => $row['quantity'],
                        'price' => $row['price'],
                        'total_price' => $row['total'],
                    ]);
                }

                $order->statusHistories()->delete();
                foreach ($timeline['histories'] as $history) {
                    $order->statusHistories()->create($history + ['changed_by' => $actorId]);
                }
            }
        });
    }

    /** @return array<string, mixed> */
    private function timeline(string $status, Carbon $createdAt, int $number): array
    {
        $result = [
            'confirmed_at' => null, 'processing_at' => null, 'shipped_at' => null,
            'delivered_at' => null, 'cancelled_at' => null,
            'histories' => [[
                'from_status' => null, 'to_status' => 'pending',
                'note' => 'Đơn hàng mẫu được tạo.', 'created_at' => $createdAt,
            ]],
        ];
        $current = 'pending';
        $steps = match ($status) {
            'pending' => [],
            'confirmed' => ['confirmed'],
            'processing' => ['confirmed', 'processing'],
            'shipped' => ['confirmed', 'processing', 'shipped'],
            'delivered' => ['confirmed', 'processing', 'shipped', 'delivered'],
            'cancelled' => array_slice(['confirmed', 'processing'], 0, $number % 3),
        };

        foreach ($steps as $index => $next) {
            $time = $createdAt->copy()->addHours(($index + 1) * 4);
            $result[$next.'_at'] = $time;
            $result['histories'][] = ['from_status' => $current, 'to_status' => $next, 'note' => null, 'created_at' => $time];
            $current = $next;
        }
        if ($status === 'cancelled') {
            $time = $createdAt->copy()->addHours((count($steps) + 1) * 4);
            $result['cancelled_at'] = $time;
            $result['histories'][] = ['from_status' => $current, 'to_status' => 'cancelled', 'note' => 'Đơn hàng mẫu được hủy.', 'created_at' => $time];
        }

        return $result;
    }
}
