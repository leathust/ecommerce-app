<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/request-urls', static fn () => response()->json([
            'root' => url('/'),
            'asset' => asset('assets/frontend/css/styles.css'),
        ]));
    }

    public function test_localhost_request_keeps_http_scheme(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost',
        ])->get('/_test/request-urls')
            ->assertOk()
            ->assertJson([
                'root' => 'http://localhost',
                'asset' => 'http://localhost/assets/frontend/css/styles.css',
            ]);
    }

    public function test_cloudflare_tunnel_headers_generate_https_urls(): void
    {
        $host = 'align-strengthening-realize-invitations.trycloudflare.com';

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost',
            'HTTP_X_FORWARDED_HOST' => $host,
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/_test/request-urls')
            ->assertOk()
            ->assertJson([
                'root' => "https://{$host}",
                'asset' => "https://{$host}/assets/frontend/css/styles.css",
            ]);
    }

    public function test_untrusted_client_cannot_spoof_forwarded_scheme_or_host(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => 'localhost',
            'HTTP_X_FORWARDED_HOST' => 'attacker.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/_test/request-urls')
            ->assertOk()
            ->assertJson([
                'root' => 'http://localhost',
                'asset' => 'http://localhost/assets/frontend/css/styles.css',
            ]);
    }
}
