<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $duplicateUserIds = DB::table('carts')
                ->select('user_id')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($duplicateUserIds as $userId) {
                $carts = DB::table('carts')
                    ->where('user_id', $userId)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get();

                $canonical = $carts->first();
                $duplicateIds = $carts->skip(1)->pluck('id');

                if (! $canonical || $duplicateIds->isEmpty()) {
                    continue;
                }

                $cartIds = $carts->pluck('id');
                $quantities = DB::table('cart_items')
                    ->whereIn('cart_id', $cartIds)
                    ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                    ->groupBy('product_id')
                    ->get();
                $stocks = DB::table('products')
                    ->whereIn('id', $quantities->pluck('product_id'))
                    ->pluck('quantity', 'id');
                $timestamp = now();

                foreach ($quantities as $item) {
                    $stock = (int) ($stocks[$item->product_id] ?? 0);
                    $limit = $stock > 0 ? min($stock, 99) : 99;
                    $quantity = max(1, min((int) $item->total_quantity, $limit));

                    $existingItem = DB::table('cart_items')
                        ->where('cart_id', $canonical->id)
                        ->where('product_id', $item->product_id)
                        ->first();

                    if ($existingItem) {
                        DB::table('cart_items')->where('id', $existingItem->id)->update([
                            'quantity' => $quantity,
                            'updated_at' => $timestamp,
                        ]);
                    } else {
                        DB::table('cart_items')->insert([
                            'cart_id' => $canonical->id,
                            'product_id' => $item->product_id,
                            'quantity' => $quantity,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    }
                }

                DB::table('cart_items')->whereIn('cart_id', $duplicateIds)->delete();
                DB::table('carts')->whereIn('id', $duplicateIds)->delete();
            }

            DB::table('carts')->whereNotNull('user_id')->update(['session_token' => null]);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->unique('user_id', 'carts_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropUnique('carts_user_id_unique');
        });
    }
};
