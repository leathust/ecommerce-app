<?php

namespace App\Services;

use App\Models\HomeBanner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HomeBannerService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes, ?Request $request = null): HomeBanner
    {
        $actor->can('banners.create') || abort(403);

        $banner = DB::transaction(function () use ($actor, $attributes, $request): HomeBanner {
            $banner = HomeBanner::create([...$attributes, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'home_banner.created', $banner, [], ['banner' => $this->snapshot($banner)], $request);

            return $banner;
        });
        return $banner;
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, HomeBanner $banner, array $attributes, ?Request $request = null): HomeBanner
    {
        $actor->can('banners.update') || abort(403);

        $updated = DB::transaction(function () use ($actor, $banner, $attributes, $request): HomeBanner {
            $locked = HomeBanner::query()->whereKey($banner)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);
            $locked->update([...$attributes, 'updated_by' => $actor->id]);
            $this->audit->record($actor, 'home_banner.updated', $locked, ['banner' => $before], ['banner' => $this->snapshot($locked)], $request);

            return $locked;
        });
        return $updated;
    }

    public function delete(User $actor, HomeBanner $banner, ?Request $request = null): void
    {
        $actor->can('banners.delete') || abort(403);

        DB::transaction(function () use ($actor, $banner, $request): void {
            $locked = HomeBanner::query()->whereKey($banner)->lockForUpdate()->firstOrFail();
            $this->audit->record($actor, 'home_banner.deleted', $locked, ['banner' => $this->snapshot($locked)], [], $request);
            $locked->delete();
        });
    }

    /** @return array{hero: array<int, array<string, mixed>>, discount: array<int, array<string, mixed>>} */
    public function published(?string $locale = null): array
    {
        $locale = $locale === 'en' ? 'en' : 'vi';
        $items = HomeBanner::query()
            ->published()
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('placement');

        return [
            'hero' => $items->get('hero', collect())->take(6)->map(fn (HomeBanner $banner) => $this->present($banner, $locale))->values()->all(),
            'discount' => $items->get('discount', collect())->take(2)->map(fn (HomeBanner $banner) => $this->present($banner, $locale))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(HomeBanner $banner, string $locale): array
    {
        return [
            'id' => $banner->id,
            'title' => $this->localized($banner, 'title', $locale),
            'eyebrow' => $this->localized($banner, 'eyebrow', $locale),
            'description' => $this->localized($banner, 'description', $locale),
            'button' => $this->localized($banner, 'button', $locale),
            'image_url' => $banner->image_url ?: asset('assets/frontend/images/product-placeholder.svg'),
            'url' => filled($banner->link_url) ? url('/'.$banner->link_url) : null,
            'theme' => $banner->theme,
        ];
    }

    private function localized(HomeBanner $banner, string $field, string $locale): string
    {
        $value = trim((string) $banner->getAttribute($field.'_'.$locale));
        if ($value !== '') {
            return $value;
        }

        $fallback = trim((string) $banner->getAttribute($field.'_vi'));

        return $fallback !== '' ? $fallback : trim((string) $banner->getAttribute($field.'_en'));
    }

    /** @return array<string, mixed> */
    private function snapshot(HomeBanner $banner): array
    {
        return Arr::only($banner->getAttributes(), [
            'placement', 'title_vi', 'title_en', 'eyebrow_vi', 'eyebrow_en',
            'description_vi', 'description_en', 'button_vi', 'button_en',
            'image_url', 'link_url', 'theme', 'sort_order', 'status', 'starts_at', 'ends_at',
        ]);
    }
}
