<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartMutationRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\Product;
use App\Services\CartService;
use App\Support\SafeStorefrontRedirect;
use App\Support\StorefrontCartContext;
use App\Support\CartPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function index(Request $request, StorefrontCartContext $context, CartPageData $page): View|JsonResponse
    {
        $cartPage = $page->build($context->cart(false), app()->getLocale());

        if ($request->expectsJson()) {
            return response()->json($this->cartViewPayload($cartPage));
        }

        return view('frontend.cart', ['cartPage' => $cartPage]);
    }

    public function store(
        StoreCartItemRequest $request,
        Product $storefrontProduct,
        StorefrontCartContext $context,
        CartService $carts,
        CartPageData $page,
        SafeStorefrontRedirect $redirect,
    ): JsonResponse|RedirectResponse {
        $cart = $context->cart(true);
        abort_unless($cart, Response::HTTP_FORBIDDEN);

        $result = $carts->add($cart, $storefrontProduct, $request->integer('quantity'));
        $message = __('frontend.cart.added');

        return $this->respond(
            $request,
            array_merge($result, ['message' => $message, 'product_id' => $storefrontProduct->id]),
            $message,
            $redirect,
            $cart,
            $page,
            $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    public function update(
        UpdateCartItemRequest $request,
        Product $product,
        StorefrontCartContext $context,
        CartService $carts,
        CartPageData $page,
        SafeStorefrontRedirect $redirect,
    ): JsonResponse|RedirectResponse {
        $cart = $context->cart(false);
        abort_unless($cart, Response::HTTP_NOT_FOUND);

        $result = $carts->update($cart, $product, $request->integer('quantity'));
        $message = __('frontend.cart.updated');

        return $this->respond(
            $request,
            array_merge($result, ['message' => $message, 'product_id' => $product->id]),
            $message,
            $redirect,
            $cart,
            $page,
        );
    }

    public function destroy(
        CartMutationRequest $request,
        Product $product,
        StorefrontCartContext $context,
        CartService $carts,
        CartPageData $page,
        SafeStorefrontRedirect $redirect,
    ): JsonResponse|RedirectResponse {
        $cart = $context->cart(false);
        $result = $carts->remove($cart, $product);
        $message = __($result['removed'] ? 'frontend.cart.removed' : 'frontend.cart.already_removed');

        return $this->respond(
            $request,
            array_merge($result, ['message' => $message, 'product_id' => $product->id]),
            $message,
            $redirect,
            $cart,
            $page,
        );
    }

    public function clear(
        CartMutationRequest $request,
        StorefrontCartContext $context,
        CartService $carts,
        CartPageData $page,
        SafeStorefrontRedirect $redirect,
    ): JsonResponse|RedirectResponse {
        $cart = $context->cart(false);
        $result = $carts->clear($cart);
        $message = __($result['removed_lines'] > 0 ? 'frontend.cart.cleared' : 'frontend.cart.already_empty');

        return $this->respond(
            $request,
            array_merge($result, ['message' => $message]),
            $message,
            $redirect,
            $cart,
            $page,
        );
    }

    /** @param array<string, mixed> $payload */
    private function respond(
        CartMutationRequest $request,
        array $payload,
        string $message,
        SafeStorefrontRedirect $redirect,
        ?Cart $cart,
        CartPageData $page,
        int $status = Response::HTTP_OK,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            $cartPage = $page->build($cart, app()->getLocale());

            return response()->json(array_merge($payload, $this->cartViewPayload($cartPage)), $status);
        }

        $returnUrl = $redirect->resolve($request, $request->input('return_to'))
            ?? $redirect->resolve($request, $request->headers->get('referer'))
            ?? route('cart');

        return redirect()->to($returnUrl)->with('cart_success', $message);
    }

    /** @param array<string, mixed> $cartPage
     *  @return array{cart_html: string, cart_count: int, line_count: int, subtotal: string}
     */
    private function cartViewPayload(array $cartPage): array
    {
        return [
            'cart_html' => view('frontend.cart.partials.contents', ['cartPage' => $cartPage])->render(),
            'cart_count' => (int) $cartPage['available_quantity'],
            'line_count' => (int) $cartPage['available_lines'],
            'subtotal' => (string) $cartPage['subtotal'],
        ];
    }
}
