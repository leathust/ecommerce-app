<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBrandRequest;
use App\Models\Brand;
use App\Services\CatalogImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBrandController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $brands = Brand::withCount('products')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name_vi', 'like', '%'.$search.'%')->orWhere('name_en', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.brands.index', compact('brands', 'filters'));
    }

    public function create(): View
    {
        return view('admin.brands.form', ['brand' => new Brand(['status' => true])]);
    }

    public function store(SaveBrandRequest $request, CatalogImageService $images): RedirectResponse
    {
        $images->save(new Brand, $request->validated(), 'brands');

        return redirect()->route('admin.brands.index')->with('success', 'Đã thêm thương hiệu.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.form', compact('brand'));
    }

    public function update(SaveBrandRequest $request, Brand $brand, CatalogImageService $images): RedirectResponse
    {
        $images->save($brand, $request->validated(), 'brands');

        return redirect()->route('admin.brands.index')->with('success', 'Đã cập nhật thương hiệu.');
    }

    public function destroy(Brand $brand, CatalogImageService $images): RedirectResponse
    {
        $path = $brand->image_url;
        $brand->delete();
        $images->deleteFile($path);

        return redirect()->route('admin.brands.index')->with('success', 'Đã xóa thương hiệu. Các sản phẩm liên quan vẫn được giữ và không còn gắn thương hiệu này.');
    }
}
