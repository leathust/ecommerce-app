@extends('frontend.layout')

@section('title', __('frontend.auth.login_title'))
@section('robots', 'noindex,nofollow')

@section('content')
<main id="main" class="auth-page">
  <div class="container auth-shell">
    <section class="auth-card" aria-labelledby="login-title">
      <div class="auth-card__heading">
        <span class="eyebrow">{{ __('frontend.auth.account') }}</span>
        <h1 id="login-title">{{ __('frontend.auth.login_title') }}</h1>
        <p>{{ __('frontend.auth.login_intro') }}</p>
      </div>

      @if ($errors->any())
        <div class="form-alert form-alert--error" role="alert">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('login.submit') }}" class="auth-form">
        @csrf
        @if ($returnTo)
          <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif

        <div class="field">
          <label for="login-email">{{ __('frontend.auth.email') }}</label>
          <input id="login-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required autofocus>
        </div>

        <div class="field">
          <label for="login-password">{{ __('frontend.auth.password') }}</label>
          <input id="login-password" name="password" type="password" autocomplete="current-password" required>
        </div>

        <label class="auth-remember">
          <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
          <span>{{ __('frontend.auth.remember') }}</span>
        </label>

        <button class="btn btn--indigo btn--block" type="submit">{{ __('frontend.auth.login_submit') }}</button>
      </form>

      @if($googleLoginEnabled ?? false)
      <div class="auth-divider"><span>{{ __('frontend.auth.or') }}</span></div>

      <a href="{{ route('login.google', $returnTo ? ['return_to' => $returnTo] : []) }}" class="btn btn--google btn--block">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
          <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/>
          <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 18.9 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
          <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
          <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C41.1 35.4 44 30.2 44 24c0-1.3-.1-2.6-.4-3.9z"/>
        </svg>
        <span>{{ __('frontend.auth.google_button') }}</span>
      </a>
      @endif

      <p class="auth-card__note">{{ __('frontend.auth.customer_only') }}</p>
    </section>
  </div>
</main>
@endsection
