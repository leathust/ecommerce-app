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

      <p class="auth-card__note">{{ __('frontend.auth.customer_only') }}</p>
    </section>
  </div>
</main>
@endsection
