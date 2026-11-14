<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWishlistRequest;
use App\Models\Product;
use App\Services\WishlistService;
use App\Support\SafeStorefrontRedirect;
use App\Support\StorefrontProductPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WishlistController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request, StorefrontProductPresenter $presenter): View
    {
        $products = $request->user()
            ->wishlistProducts()
            ->published()
            ->with(['primaryImage', 'category', 'brand'])
            ->orderByPivot('created_at', 'desc')
            ->orderByDesc('products.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $products->setCollection(
            $products->getCollection()->map(
                fn (Product $product): array => $presenter->present($product, app()->getLocale()),
            ),
        );

        return view('frontend.wishlist.index', compact('products'));
    }

    public function store(
        StoreWishlistRequest $request,
        Product $storefrontProduct,
        WishlistService $wishlists,
        SafeStorefrontRedirect $redirect,
    ): JsonResponse|RedirectResponse {
        $result = $wishlists->add($request->user(), $storefrontProduct);
        $message = __($result['created'] ? 'frontend.wishlist.added' : 'frontend.wishlist.already_added');

        if ($request->expectsJson()) {
            return response()->json([
                'is_wishlisted' => true,
                'wishlist_count' => $result['count'],
                'message' => $message,
            ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
        }

        $returnUrl = $this->normalizeWishlistPage($this->returnUrl($request, $redirect), $result['count']);

        return redirect()->to($returnUrl)->with('wishlist_success', $message);
    }

    public function destroy(
        Request $request,
        Product $product,
        WishlistService $wishlists,
        SafeStorefrontRedirect $redirect,
    ): JsonResponse|RedirectResponse {
        $result = $wishlists->remove($request->user(), $product);
        $message = __($result['removed'] ? 'frontend.wishlist.removed' : 'frontend.wishlist.already_removed');

        if ($request->expectsJson()) {
            return response()->json([
                'is_wishlisted' => false,
                'wishlist_count' => $result['count'],
                'message' => $message,
            ]);
        }

        $returnUrl = $this->normalizeWishlistPage($this->returnUrl($request, $redirect), $result['count']);

        return redirect()->to($returnUrl)->with('wishlist_success', $message);
    }

    private function returnUrl(Request $request, SafeStorefrontRedirect $redirect): string
    {
        return $redirect->resolve($request, $request->input('return_to'))
            ?? $redirect->resolve($request, $request->headers->get('referer'))
            ?? route('shop');
    }

    private function normalizeWishlistPage(string $url, int $count): string
    {
        $wishlistPath = '/'.ltrim((string) parse_url(route('wishlist.index'), PHP_URL_PATH), '/');
        $returnPath = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($returnPath !== $wishlistPath) {
            return $url;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $requestedPage = max(1, filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
        $lastPage = max(1, (int) ceil($count / self::PER_PAGE));

        $targetPage = min($requestedPage, $lastPage);

        return route('wishlist.index', $targetPage > 1 ? ['page' => $targetPage] : []);
    }
}
