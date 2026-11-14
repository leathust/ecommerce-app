<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveHomeBannerRequest;
use App\Models\HomeBanner;
use App\Services\HomeBannerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminHomeBannerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'placement' => ['nullable', Rule::in(HomeBanner::PLACEMENTS)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'scheduled', 'expired'])],
        ]);
        $now = now();
        $banners = HomeBanner::query()
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('title_vi', 'like', '%'.$search.'%')
                ->orWhere('title_en', 'like', '%'.$search.'%')))
            ->when($filters['placement'] ?? null, fn ($query, $placement) => $query->where('placement', $placement))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->published($now))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('status', false))
            ->when(($filters['status'] ?? null) === 'scheduled', fn ($query) => $query->where('status', true)->where('starts_at', '>', $now))
            ->when(($filters['status'] ?? null) === 'expired', fn ($query) => $query->whereNotNull('ends_at')->where('ends_at', '<=', $now))
            ->orderBy('placement')->orderBy('sort_order')->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('admin.home-banners.index', compact('banners', 'filters', 'now'));
    }

    public function create(): View
    {
        return view('admin.home-banners.form', ['homeBanner' => new HomeBanner([
            'placement' => 'hero', 'theme' => 'purple', 'status' => true, 'sort_order' => 0,
        ])]);
    }

    public function store(SaveHomeBannerRequest $request, HomeBannerService $service): RedirectResponse
    {
        $service->create($request->user(), $request->validated(), $request);

        return redirect()->route('admin.home-banners.index')->with('success', 'Đã tạo banner trang chủ.');
    }

    public function edit(HomeBanner $homeBanner): View
    {
        return view('admin.home-banners.form', compact('homeBanner'));
    }

    public function update(SaveHomeBannerRequest $request, HomeBanner $homeBanner, HomeBannerService $service): RedirectResponse
    {
        $service->update($request->user(), $homeBanner, $request->validated(), $request);

        return redirect()->route('admin.home-banners.index')->with('success', 'Đã cập nhật banner trang chủ.');
    }

    public function destroy(Request $request, HomeBanner $homeBanner, HomeBannerService $service): RedirectResponse
    {
        $service->delete($request->user(), $homeBanner, $request);

        return redirect()->route('admin.home-banners.index')->with('success', 'Đã xóa banner trang chủ.');
    }
}
