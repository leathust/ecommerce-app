<?php

namespace App\Services;

class GoogleOAuthService
{
    public function __construct(private readonly SystemSettingService $settings)
    {
    }

    /**
     * Google login is available only when the admin toggle is on and both credentials exist.
     */
    public function enabled(): bool
    {
        return (bool) $this->settings->get('auth.google_login_enabled') && $this->configured();
    }

    public function configured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    public function clientId(): string
    {
        return (string) ($this->settings->get('auth.google_client_id') ?: config('services.google.client_id'));
    }

    public function clientSecret(): string
    {
        return (string) ($this->settings->get('auth.google_client_secret') ?: config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        $configured = (string) $this->settings->get('auth.google_redirect_uri');

        return $configured !== '' ? $configured : route('login.google.callback');
    }

    /**
     * Push the admin-managed credentials into the Socialite google driver config.
     */
    public function applyToConfig(): void
    {
        config([
            'services.google.client_id' => $this->clientId(),
            'services.google.client_secret' => $this->clientSecret(),
            'services.google.redirect' => $this->redirectUri(),
        ]);
    }
}
