<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\User;
use App\Services\CartIdentityService;

class StorefrontCartContext
{
    private bool $resolved = false;

    private bool $created = false;

    private ?int $actorId = null;

    private ?string $guestTokenHash = null;

    private ?Cart $cart = null;

    private bool $dataResolved = false;

    /** @var array{count: int} */
    private array $resolvedData = ['count' => 0];

    public function __construct(
        private readonly CartIdentityService $identity,
    ) {
    }

    public function cart(bool $create = false): ?Cart
    {
        $request = request();
        $user = $request->user();
        $actorId = $user instanceof User ? (int) $user->getKey() : null;
        $rawToken = $request->cookie(CartIdentityService::COOKIE_NAME);
        $guestTokenHash = is_string($rawToken) ? $this->identity->hashToken($rawToken) : null;

        if ($this->resolved
            && $this->actorId === $actorId
            && $this->guestTokenHash === $guestTokenHash
            && (! $create || $this->created)) {
            return $this->cart;
        }

        if ($this->actorId !== $actorId || $this->guestTokenHash !== $guestTokenHash) {
            $this->dataResolved = false;
            $this->resolvedData = ['count' => 0];
        }

        $this->resolved = true;
        $this->created = $create;
        $this->actorId = $actorId;
        $this->guestTokenHash = $guestTokenHash;

        return $this->cart = $this->identity->resolve($request, $create);
    }

    public function reset(): void
    {
        $this->resolved = false;
        $this->created = false;
        $this->actorId = null;
        $this->guestTokenHash = null;
        $this->cart = null;
        $this->dataResolved = false;
        $this->resolvedData = ['count' => 0];
    }

    /** @return array{count: int} */
    public function data(): array
    {
        $cart = $this->cart(false);

        if ($this->dataResolved) {
            return $this->resolvedData;
        }

        $this->dataResolved = true;

        if (! $cart) {
            return $this->resolvedData;
        }

        $quantities = $cart->items()
            ->join('products', 'products.id', '=', 'cart_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.status', true)
            ->where('categories.status', true)
            ->where('products.quantity', '>', 0)
            ->get(['cart_items.quantity as cart_quantity', 'products.quantity as stock_quantity']);

        return $this->resolvedData = [
            'count' => $quantities->sum(
                fn (object $row): int => min(
                    (int) $row->cart_quantity,
                    (int) $row->stock_quantity,
                    CartIdentityService::MAX_LINE_QUANTITY,
                ),
            ),
        ];
    }
}
