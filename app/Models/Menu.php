<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'parent_id',
        'name_vi',
        'name_en',
        'link_type',
        'route_name',
        'url',
        'target',
        'location',
        'sort_order',
        'status',
    ];

    protected $attributes = [
        'link_type' => 'url',
        'target' => '_self',
        'location' => 'header',
        'sort_order' => 0,
        'status' => true,
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeForLocation(Builder $query, string $location): Builder
    {
        return $query->where('location', $location);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function localizedName(?string $locale = null): string
    {
        $supportedLocales = config('menus.locales', ['vi', 'en']);
        $fallbackLocale = config('menus.fallback_locale', 'vi');
        $locale = in_array($locale, $supportedLocales, true) ? $locale : $fallbackLocale;
        $alternateLocale = $locale === 'vi' ? 'en' : 'vi';

        return (string) ($this->getAttribute('name_'.$locale)
            ?: $this->getAttribute('name_'.$alternateLocale)
            ?: '');
    }

    public function isContainer(): bool
    {
        return $this->link_type === 'none';
    }
}
