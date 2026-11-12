<?php

namespace App\Support;

use App\Services\SystemSettingService;
use Illuminate\Http\Request;

class StorefrontLocaleResolver
{
    public const SESSION_KEY = 'storefront_locale';

    public function __construct(private readonly SystemSettingService $settings) {}

    public function resolve(Request $request): string
    {
        return $this->normalize($request->session()->get(self::SESSION_KEY))
            ?? $this->normalize($this->settings->get('site.default_locale', null))
            ?? $this->normalize(config('app.locale'))
            ?? $this->fallbackLocale();
    }

    /** @return array<int, string> */
    public function supportedLocales(): array
    {
        $locales = array_values(array_unique(array_filter(
            config('menus.locales', ['vi', 'en']),
            static fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '',
        )));

        return $locales !== [] ? $locales : ['vi', 'en'];
    }

    public function normalize(mixed $locale): ?string
    {
        if (! is_string($locale)) {
            return null;
        }

        $locale = strtolower(trim($locale));

        return in_array($locale, $this->supportedLocales(), true) ? $locale : null;
    }

    private function fallbackLocale(): string
    {
        return $this->normalize(config('menus.fallback_locale')) ?? 'vi';
    }
}
