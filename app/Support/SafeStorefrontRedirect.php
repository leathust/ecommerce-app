<?php

namespace App\Support;

use Illuminate\Http\Request;

class SafeStorefrontRedirect
{
    public function resolve(Request $request, mixed $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $candidate)) {
            return null;
        }

        if (str_starts_with($candidate, '//')) {
            return null;
        }

        if (str_starts_with($candidate, '/')) {
            return $this->isStorefrontPath($candidate) ? $candidate : null;
        }

        if (! filter_var($candidate, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($candidate);
        $requestPort = $request->getPort();
        $candidatePort = $parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80);

        if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || strtolower((string) ($parts['scheme'] ?? '')) !== strtolower($request->getScheme())
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower($request->getHost())
            || $candidatePort !== $requestPort
        ) {
            return null;
        }

        return $this->isStorefrontPath(($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''))
            ? $candidate
            : null;
    }

    private function isStorefrontPath(string $url): bool
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '/login'
            && $path !== '/logout'
            && $path !== '/admin'
            && ! str_starts_with($path, '/admin/');
    }
}
