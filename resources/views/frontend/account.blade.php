@extends('frontend.layout')

@section('title', __('frontend.profile.page_title'))
@section('robots', 'noindex,nofollow')

@section('content')
<main id="main" class="account-page">
  <div class="container account-shell">
    <header class="account-hero" aria-labelledby="account-title">
      <span class="eyebrow">{{ __('frontend.profile.eyebrow') }}</span>
      <h1 id="account-title" data-profile-welcome data-welcome-template="{{ __('frontend.auth.welcome', ['name' => '__NAME__']) }}">{{ __('frontend.auth.welcome', ['name' => $account['display_name']]) }}</h1>
      <p>{{ __('frontend.profile.page_intro') }}</p>
    </header>

    @if (session('profile_success'))
      <div class="form-alert form-alert--success account-feedback" role="status">{{ session('profile_success') }}</div>
    @endif
    @if (session('password_success'))
      <div class="form-alert form-alert--success account-feedback" role="status">{{ session('password_success') }}</div>
    @endif
    <div class="form-alert account-feedback account-feedback--ajax" data-profile-feedback data-error-message="{{ __('frontend.profile.request_error') }}" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden></div>

    <div class="account-layout">
      <aside class="account-sidebar" aria-label="{{ __('frontend.profile.overview_title') }}">
        <section class="account-panel account-panel--overview" aria-labelledby="profile-overview-title">
          <div class="account-avatar" data-profile-avatar aria-hidden="true">{{ mb_strtoupper(mb_substr($account['display_name'], 0, 1)) }}</div>
          <div class="account-panel__heading account-panel__heading--center">
            <h2 id="profile-overview-title">{{ __('frontend.profile.overview_title') }}</h2>
            <p data-profile-display-name>{{ $account['display_name'] }}</p>
          </div>

          <dl class="account-summary">
            <div><dt>{{ __('frontend.auth.email') }}</dt><dd>{{ $account['email'] }}</dd></div>
            <div><dt>{{ __('frontend.profile.username') }}</dt><dd>{{ $account['username'] ?: __('frontend.profile.not_provided') }}</dd></div>
            <div><dt>{{ __('frontend.profile.status') }}</dt><dd><span class="account-status account-status--active">{{ __('frontend.profile.status_'.$account['status']) }}</span></dd></div>
            <div><dt>{{ __('frontend.profile.registered_at') }}</dt><dd>{{ $account['registered_at'] ? \Illuminate\Support\Carbon::parse($account['registered_at'])->format('d/m/Y') : __('frontend.profile.not_provided') }}</dd></div>
          </dl>

          <p class="account-readonly-note"><span aria-hidden="true">🔒</span>{{ __('frontend.profile.identity_readonly') }}</p>
        </section>

        <nav class="account-panel account-shortcuts" aria-label="{{ __('frontend.profile.shortcuts_title') }}">
          <h2>{{ __('frontend.profile.shortcuts_title') }}</h2>
          <a href="{{ $accountLinks['wishlist'] }}"><span aria-hidden="true">♡</span><span>{{ __('frontend.wishlist.title') }}</span><span aria-hidden="true">→</span></a>
          <a href="{{ $accountLinks['cart'] }}"><span aria-hidden="true">◇</span><span>{{ __('frontend.cart.title') }}</span><span aria-hidden="true">→</span></a>
        </nav>

        <section class="account-panel account-session" aria-labelledby="session-title">
          <h2 id="session-title">{{ __('frontend.profile.session_title') }}</h2>
          <p>{{ __('frontend.profile.session_intro') }}</p>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn--ghost btn--block account-logout" type="submit">{{ __('frontend.auth.logout') }}</button>
          </form>
        </section>
      </aside>

      <div class="account-content">
        <section class="account-panel" aria-labelledby="personal-info-title">
          <div class="account-panel__heading">
            <span class="account-section-icon" aria-hidden="true">✎</span>
            <div><h2 id="personal-info-title">{{ __('frontend.profile.personal_title') }}</h2><p>{{ __('frontend.profile.personal_intro') }}</p></div>
          </div>

          @if ($errors->profile->any())
            <div class="form-alert form-alert--error" role="alert">{{ $errors->profile->first() }}</div>
          @endif

          <form method="POST" action="{{ route('storefront.profile.update') }}" class="account-form" data-profile-form="profile" data-pending-text="{{ __('frontend.profile.saving') }}">
            @csrf
            @method('PATCH')
            <div class="field-row">
              <div class="field">
                <label for="profile-first-name">{{ __('frontend.profile.first_name') }}</label>
                <input id="profile-first-name" name="first_name" type="text" value="{{ old('first_name', $account['first_name']) }}" maxlength="100" autocomplete="given-name" @if($errors->profile->has('first_name')) aria-invalid="true" aria-describedby="profile-first-name-error" @endif>
                @if ($errors->profile->has('first_name'))<small id="profile-first-name-error" class="field-error">{{ $errors->profile->first('first_name') }}</small>@endif
              </div>
              <div class="field">
                <label for="profile-last-name">{{ __('frontend.profile.last_name') }}</label>
                <input id="profile-last-name" name="last_name" type="text" value="{{ old('last_name', $account['last_name']) }}" maxlength="100" autocomplete="family-name" @if($errors->profile->has('last_name')) aria-invalid="true" aria-describedby="profile-last-name-error" @endif>
                @if ($errors->profile->has('last_name'))<small id="profile-last-name-error" class="field-error">{{ $errors->profile->first('last_name') }}</small>@endif
              </div>
            </div>
            <div class="field">
              <label for="profile-phone">{{ __('frontend.profile.phone') }}</label>
              <input id="profile-phone" name="phone" type="tel" value="{{ old('phone', $account['phone']) }}" maxlength="30" autocomplete="tel" @if($errors->profile->has('phone')) aria-invalid="true" aria-describedby="profile-phone-error" @endif>
              @if ($errors->profile->has('phone'))<small id="profile-phone-error" class="field-error">{{ $errors->profile->first('phone') }}</small>@else<small class="field-hint">{{ __('frontend.profile.phone_hint') }}</small>@endif
            </div>
            <button class="btn btn--indigo account-form__submit" data-profile-submit type="submit">{{ __('frontend.profile.save_profile') }}</button>
          </form>
        </section>

        <section class="account-panel" aria-labelledby="password-title">
          <div class="account-panel__heading">
            <span class="account-section-icon" aria-hidden="true">⌁</span>
            <div><h2 id="password-title">{{ __('frontend.profile.password_title') }}</h2><p>{{ __('frontend.profile.password_intro') }}</p></div>
          </div>

          @if ($errors->password->any())
            <div class="form-alert form-alert--error" role="alert">{{ $errors->password->first() }}</div>
          @endif

          <form method="POST" action="{{ route('storefront.password.update') }}" class="account-form" data-profile-form="password" data-pending-text="{{ __('frontend.profile.saving') }}">
            @csrf
            @method('PUT')
            <div class="field">
              <label for="profile-current-password">{{ __('frontend.profile.current_password') }}</label>
              <input id="profile-current-password" name="current_password" type="password" autocomplete="current-password" required @if($errors->password->has('current_password')) aria-invalid="true" aria-describedby="profile-current-password-error" @endif>
              @if ($errors->password->has('current_password'))<small id="profile-current-password-error" class="field-error">{{ $errors->password->first('current_password') }}</small>@endif
            </div>
            <div class="field-row">
              <div class="field">
                <label for="profile-new-password">{{ __('frontend.profile.new_password') }}</label>
                <input id="profile-new-password" name="password" type="password" autocomplete="new-password" required @if($errors->password->has('password')) aria-invalid="true" aria-describedby="profile-new-password-error" @endif>
                @if ($errors->password->has('password'))<small id="profile-new-password-error" class="field-error">{{ $errors->password->first('password') }}</small>@endif
              </div>
              <div class="field">
                <label for="profile-password-confirmation">{{ __('frontend.profile.password_confirmation') }}</label>
                <input id="profile-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
              </div>
            </div>
            <p class="account-password-hint">{{ __('frontend.profile.password_hint') }}</p>
            <button class="btn btn--ink account-form__submit" data-profile-submit type="submit">{{ __('frontend.profile.save_password') }}</button>
          </form>
        </section>
      </div>
    </div>
  </div>
</main>
@endsection
