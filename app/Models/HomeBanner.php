<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeBanner extends Model
{
    public const PLACEMENTS = ['hero', 'discount'];
    public const THEMES = ['purple', 'teal', 'orange', 'green', 'black', 'watch', 'airpods'];

    protected $fillable = [
        'placement', 'title_vi', 'title_en', 'eyebrow_vi', 'eyebrow_en',
        'description_vi', 'description_en', 'button_vi', 'button_en',
        'image_url', 'link_url', 'theme', 'sort_order', 'status',
        'starts_at', 'ends_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query, $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('status', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }
}
