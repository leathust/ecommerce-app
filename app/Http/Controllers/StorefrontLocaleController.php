<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStorefrontLocaleRequest;
use App\Support\StorefrontLocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorefrontLocaleController extends Controller
{
    public function update(
        UpdateStorefrontLocaleRequest $request,
        StorefrontLocaleResolver $resolver,
    ): RedirectResponse {
        $locale = $resolver->normalize($request->validated('locale'));

        // Form Request validation guarantees this value belongs to the allowlist.
        $request->session()->put(StorefrontLocaleResolver::SESSION_KEY, $locale);

        return redirect()->to($this->redirectTarget(
            $request,
            $request->validated('return_url'),
        ));
    }

    public function legacyRedirect(): RedirectResponse
    {
        return redirect()->route('home');
    }

    private function redirectTarget(Request $request, mixed $requestedUrl): string
    {
        foreach ([$requestedUrl, url()->previous()] as $candidate) {
            $safeUrl = $this->safeInternalUrl($request, $candidate);

            if ($safeUrl !== null) {
                return $safeUrl;
            }
        }

        return route('home');
    }

    private function safeInternalUrl(Request $request, mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '' || str_contains($candidate, '\\') || preg_match('/[\x00-\x1F\x7F]/', $candidate)) {
            return null;
        }

        $isRelative = str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//');
        $parts = parse_url($candidate);

        if ($parts === false) {
            return null;
        }

        if ($isRelative) {
            if (isset($parts['host']) || isset($parts['scheme']) || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
        } elseif (! $this->matchesRequestOrigin($request, $parts)) {
            return null;
        }

        $path = rawurldecode((string) ($parts['path'] ?? '/'));

        if ($this->isAdminPath($request, $path)) {
            return null;
        }

        return $candidate;
    }

    /** @param array<string, mixed> $parts */
    private function matchesRequestOrigin(Request $request, array $parts): bool
    {
        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        return in_array($scheme, ['http', 'https'], true)
            && $scheme === strtolower($request->getScheme())
            && $host === strtolower($request->getHost())
            && $port === $request->getPort()
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private function isAdminPath(Request $request, string $path): bool
    {
        $basePath = rtrim($request->getBaseUrl(), '/');

        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath.'/'))) {
            $path = substr($path, strlen($basePath));
        }

        $firstSegment = explode('/', ltrim(preg_replace('#/+#', '/', $path), '/'), 2)[0] ?? '';

        return strtolower($firstSegment) === 'admin';
    }
}
