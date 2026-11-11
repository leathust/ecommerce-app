<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCategoryRequest;
use App\Models\Category;
use App\Services\CatalogImageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $categories = Category::with('parent')->withCount(['products', 'children'])
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name_vi', 'like', '%'.$search.'%')->orWhere('name_en', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderBy('sort_order')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.categories.index', compact('categories', 'filters'));
    }

    public function create(): View
    {
        return $this->form(new Category(['status' => true, 'sort_order' => 0]));
    }

    public function store(SaveCategoryRequest $request, CatalogImageService $images): RedirectResponse
    {
        $images->save(new Category, $request->validated(), 'categories');

        return redirect()->route('admin.categories.index')->with('success', 'Đã thêm danh mục.');
    }

    public function edit(Category $category): View
    {
        return $this->form($category);
    }

    public function update(SaveCategoryRequest $request, Category $category, CatalogImageService $images): RedirectResponse
    {
        $images->save($category, $request->validated(), 'categories');

        return redirect()->route('admin.categories.index')->with('success', 'Đã cập nhật danh mục.');
    }

    public function destroy(Category $category, CatalogImageService $images): RedirectResponse
    {
        $path = $category->image_url;
        try {
            $category->delete();
        } catch (QueryException $exception) {
            if (! in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 19], true)) {
                throw $exception;
            }

            return back()->with('error', 'Danh mục đang chứa sản phẩm nên không thể xóa. Hãy chuyển sản phẩm sang danh mục khác hoặc ẩn danh mục.');
        }

        $images->deleteFile($path);

        return redirect()->route('admin.categories.index')->with('success', 'Đã xóa danh mục. Các danh mục con (nếu có) đã trở thành danh mục gốc.');
    }

    private function form(Category $category): View
    {
        $parents = Category::orderBy('name_vi')->get();
        // Hide the current category and all its descendants from parent choices.
        $excluded = $category->exists ? [$category->id] : [];
        do {
            $count = count($excluded);
            foreach ($parents as $candidate) {
                if (in_array($candidate->parent_id, $excluded, true) && ! in_array($candidate->id, $excluded, true)) {
                    $excluded[] = $candidate->id;
                }
            }
        } while (count($excluded) > $count);

        return view('admin.categories.form', ['category' => $category, 'parents' => $parents->whereNotIn('id', $excluded)]);
    }
}
