<?php

$trustedProxies = array_values(array_filter(array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
)));

return [
    /*
    | Cloudflare Tunnel connects to XAMPP through the local machine. Trusting
    | loopback lets Laravel honor X-Forwarded-Proto/Host for the tunnel while
    | direct localhost requests continue to use their original HTTP scheme.
    */
    'trusted' => $trustedProxies,
];
