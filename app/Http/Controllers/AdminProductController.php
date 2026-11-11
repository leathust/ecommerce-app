<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteProductsRequest;
use App\Http\Requests\SaveProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductDeletionService;
use App\Services\ProductImageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $products = $this->products($filters['q'] ?? null);

        if ($request->header('X-Product-Partial') === '1') {
            return view('admin.products.partials.list', compact('products'));
        }

        return view('admin.products.index', compact('products', 'filters'));
    }

    public function create(): View
    {
        return $this->form(new Product(['status' => true, 'is_featured' => false, 'quantity' => 0]));
    }

    public function store(SaveProductRequest $request, ProductImageService $images): RedirectResponse
    {
        $images->save(new Product, $request->validated());
        return redirect()->route('admin.products')->with('success', 'Đã thêm sản phẩm.');
    }

    public function edit(Product $product): View
    {
        return $this->form($product);
    }

    public function update(SaveProductRequest $request, Product $product, ProductImageService $images): RedirectResponse
    {
        $images->save($product, $request->validated());
        return redirect()->route('admin.products')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product, ProductDeletionService $deletion): RedirectResponse
    {
        if ($deletion->delete($product)['status'] === 'blocked') {
            return back()->with('error', 'Không thể xóa sản phẩm đang được tham chiếu (ví dụ trong giỏ hàng). Bạn có thể chuyển sản phẩm sang trạng thái ẩn.');
        }
        return redirect()->route('admin.products')->with('success', 'Đã xóa sản phẩm.');
    }

    public function bulkDelete(BulkDeleteProductsRequest $request, ProductDeletionService $deletion): JsonResponse
    {
        $data = $request->validated();
        $requestedIds = array_map('intval', $data['ids']);
        $foundProducts = Product::with('images')->whereIn('id', $requestedIds)->get()->keyBy('id');
        $deletedIds = [];
        $blocked = [];

        foreach ($requestedIds as $id) {
            $product = $foundProducts->get($id);
            if (! $product) { continue; }

            if ($deletion->delete($product)['status'] === 'deleted') {
                $deletedIds[] = $id;
            } else {
                $blocked[] = ['id' => $id, 'message' => 'Sản phẩm đang được tham chiếu, ví dụ trong giỏ hàng.'];
            }
        }

        $foundIds = $foundProducts->keys()->map(fn ($id) => (int) $id)->all();
        $missingIds = array_values(array_diff($requestedIds, $foundIds));
        $search = $data['q'] ?? null;
        $page = (int) ($data['page'] ?? 1);
        $products = $this->products($search, $page);
        if ($page > $products->lastPage()) {
            $page = max(1, $products->lastPage());
            $products = $this->products($search, $page);
        }

        return response()->json([
            'deleted_ids' => $deletedIds,
            'blocked' => $blocked,
            'missing_ids' => $missingIds,
            'html' => view('admin.products.partials.list', compact('products'))->render(),
            'total' => $products->total(),
            'page' => $page,
            'message' => count($deletedIds).' sản phẩm đã được xóa.',
        ]);
    }

    private function form(Product $product): View
    {
        $product->load(['images' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name_vi')->get(),
            'brands' => Brand::orderBy('name_vi')->get(),
        ]);
    }

    private function products(?string $search, ?int $page = null): LengthAwarePaginator
    {
        return Product::with(['category', 'brand', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')])
            ->when($search, function ($query, $search) {
                $query->where(fn ($query) => $query->where('name_vi', 'like', '%'.$search.'%')
                    ->orWhere('name_en', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
            })
            ->orderByDesc('id')->paginate(15, ['*'], 'page', $page)->withQueryString();
    }
}
