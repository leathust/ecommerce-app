<?php

namespace App\Support;

use App\Services\SystemSettingAssetService;
use App\Services\SystemSettingService;

class SystemSettingPresenter
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly SystemSettingAssetService $assets,
    ) {}

    /** @return array<string, mixed> */
    public function storefront(?string $locale = null): array
    {
        $values = $this->settings->publicValues();
        $locale = $this->supportedLocale($locale);
        $name = $this->localized($values, 'site.name', $locale, (string) config('app.name'));
        $ogImageUrl = $this->assets->publicUrl('seo.og_image', $values['seo.og_image'] ?? null);

        if (is_string($ogImageUrl) && str_starts_with($ogImageUrl, '/')) {
            $ogImageUrl = url($ogImageUrl);
        }

        return [
            'locale' => $locale,
            'name' => $name,
            'initial' => $this->initial($name),
            'tagline' => $this->localized($values, 'site.tagline', $locale),
            'logo_url' => $this->assets->publicUrl('site.logo', $values['site.logo'] ?? null),
            'favicon_url' => $this->assets->publicUrl('site.favicon', $values['site.favicon'] ?? null),
            'seo' => [
                'title' => $this->localized($values, 'seo.meta_title', $locale, $name),
                'description' => $this->localized($values, 'seo.meta_description', $locale),
                'og_image_url' => $ogImageUrl,
                'indexing_enabled' => app()->environment('production')
                    && (bool) ($values['seo.indexing_enabled'] ?? false),
            ],
            'social' => array_filter([
                'facebook' => $this->safeHttpUrl($values['social.facebook_url'] ?? null),
                'instagram' => $this->safeHttpUrl($values['social.instagram_url'] ?? null),
                'youtube' => $this->safeHttpUrl($values['social.youtube_url'] ?? null),
                'tiktok' => $this->safeHttpUrl($values['social.tiktok_url'] ?? null),
            ]),
            'contact' => [
                'email' => (string) ($values['contact.email'] ?? ''),
                'phone' => (string) ($values['contact.phone'] ?? ''),
                'address' => $this->localized($values, 'contact.address', $locale),
                'working_hours' => $this->localized($values, 'contact.working_hours', $locale),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function invoice(string $locale = 'vi'): array
    {
        $values = $this->settings->publicValues();
        $locale = $this->supportedLocale($locale);
        $siteName = $this->localized($values, 'site.name', $locale, (string) config('app.name'));

        return [
            'locale' => $locale,
            'site_name' => $siteName,
            'logo_url' => $this->assets->publicUrl('site.logo', $values['site.logo'] ?? null),
            'legal_name' => (string) (($values['business.legal_name'] ?? '') ?: $siteName),
            'tax_code' => (string) ($values['business.tax_code'] ?? ''),
            'address' => $this->localized($values, 'contact.address', $locale),
            'phone' => (string) ($values['contact.phone'] ?? ''),
            'email' => (string) ($values['contact.email'] ?? ''),
            'footer' => $this->localized(
                $values,
                'business.invoice_footer',
                $locale,
                $locale === 'en' ? 'Thank you for your purchase.' : 'Cảm ơn quý khách đã mua hàng.',
            ),
        ];
    }

    private function supportedLocale(?string $locale): string
    {
        return in_array($locale, ['vi', 'en'], true) ? $locale : 'vi';
    }

    /** @param array<string, mixed> $values */
    private function localized(array $values, string $baseKey, string $locale, string $fallback = ''): string
    {
        $alternate = $locale === 'vi' ? 'en' : 'vi';

        return (string) (($values[$baseKey.'_'.$locale] ?? '')
            ?: ($values[$baseKey.'_'.$alternate] ?? '')
            ?: $fallback);
    }

    private function initial(string $name): string
    {
        $name = trim($name);

        return $name === '' ? 'E' : mb_strtoupper(mb_substr($name, 0, 1));
    }

    private function safeHttpUrl(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $value
            : null;
    }
}
