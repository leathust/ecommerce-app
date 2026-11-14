@extends('frontend.layout')

@section('title', __('frontend.checkout.page_title'))
@section('robots', 'noindex,nofollow')

@section('content')
<main id="main" class="checkout-page">
  <section class="page-head">
    <div class="container">
      <div class="crumbs">
        <a href="{{ route('home') }}">{{ __('frontend.catalog.home') }}</a><span class="sep">›</span>
        <a href="{{ route('cart') }}">{{ __('frontend.cart.title') }}</a><span class="sep">›</span>
        <span>{{ __('frontend.checkout.page_title') }}</span>
      </div>
      <h1>{{ __('frontend.checkout.page_title') }}</h1>
      <p>{{ __('frontend.checkout.page_intro') }}</p>
    </div>
  </section>

  <section class="section checkout-section">
    <div class="container">
      @if ($errors->any())
        <div class="form-alert form-alert--error" role="alert">{{ $errors->first() }}</div>
      @endif
      @if (! $checkout['can_submit'])
        <div class="form-alert form-alert--error" role="alert">
          {{ $checkout['cart']['has_items'] ? __('frontend.checkout.submit_blocked') : __('frontend.checkout.cart_empty') }}
        </div>
      @endif

      <div class="checkout-layout">
        <form class="checkout-form" method="POST" action="{{ route('checkout.store') }}" data-checkout-form data-pending-text="{{ __('frontend.checkout.submitting') }}">
          @csrf
          <input type="hidden" name="idempotency_key" value="{{ $checkout['idempotency_key'] }}">

          <section class="checkout-panel" aria-labelledby="checkout-recipient-title">
            <h2 id="checkout-recipient-title">{{ __('frontend.checkout.recipient_title') }}</h2>
            <div class="field-row">
              <div class="field">
                <label for="checkout-name">{{ __('frontend.checkout.recipient_name') }}</label>
                <input id="checkout-name" name="recipient_name" type="text" required maxlength="255" autocomplete="name"
                  value="{{ old('recipient_name', $checkout['prefill']['recipient_name']) }}"
                  @if ($errors->has('recipient_name')) aria-invalid="true" aria-describedby="checkout-name-error" @endif>
                @if ($errors->has('recipient_name'))
                  <small id="checkout-name-error" class="field-error">{{ $errors->first('recipient_name') }}</small>
                @endif
              </div>
              <div class="field">
                <label for="checkout-phone">{{ __('frontend.checkout.recipient_phone') }}</label>
                <input id="checkout-phone" name="recipient_phone" type="tel" required maxlength="30" autocomplete="tel"
                  value="{{ old('recipient_phone', $checkout['prefill']['recipient_phone']) }}"
                  @if ($errors->has('recipient_phone')) aria-invalid="true" aria-describedby="checkout-phone-error" @endif>
                @if ($errors->has('recipient_phone'))
                  <small id="checkout-phone-error" class="field-error">{{ $errors->first('recipient_phone') }}</small>
                @endif
              </div>
            </div>
            <div class="field">
              <label for="checkout-address">{{ __('frontend.checkout.recipient_address') }}</label>
              <textarea id="checkout-address" name="shipping_address" required maxlength="2000" autocomplete="street-address"
                @if ($errors->has('shipping_address')) aria-invalid="true" aria-describedby="checkout-address-error" @endif>{{ old('shipping_address') }}</textarea>
              @if ($errors->has('shipping_address'))
                <small id="checkout-address-error" class="field-error">{{ $errors->first('shipping_address') }}</small>
              @endif
            </div>
            <div class="field">
              <label for="checkout-note">{{ __('frontend.checkout.note_label') }}</label>
              <textarea id="checkout-note" name="note" maxlength="2000" placeholder="{{ __('frontend.checkout.note_placeholder') }}"
                @if ($errors->has('note')) aria-invalid="true" aria-describedby="checkout-note-error" @endif>{{ old('note') }}</textarea>
              @if ($errors->has('note'))
                <small id="checkout-note-error" class="field-error">{{ $errors->first('note') }}</small>
              @endif
            </div>
          </section>

          <section class="checkout-panel" aria-labelledby="checkout-payment-title">
            <h2 id="checkout-payment-title">{{ __('frontend.checkout.payment_title') }}</h2>
            <div class="checkout-methods">
              @if ($checkout['cod_enabled'])
                <label class="checkout-option">
                  <input type="radio" name="payment_method" value="cod"
                    @checked(old('payment_method', $checkout['default_method']) === 'cod') required>
                  <span>
                    <strong>{{ __('frontend.checkout.cod_label') }}</strong>
                    <small>{{ __('frontend.checkout.cod_hint') }}</small>
                  </span>
                </label>
              @endif

              @if ($checkout['sepay_enabled'])
                <label class="checkout-option">
                  <input type="radio" name="payment_method" value="sepay"
                    @checked(old('payment_method', $checkout['default_method']) === 'sepay')>
                  <span>
                    <strong>{{ __('frontend.checkout.sepay_label') }}</strong>
                    <small>{{ __('frontend.checkout.sepay_hint', ['minutes' => $checkout['sepay_timeout_minutes']]) }}</small>
                  </span>
                </label>
              @else
                <div class="checkout-option checkout-option--disabled" aria-disabled="true">
                  <span>
                    <strong>{{ __('frontend.checkout.sepay_label') }}</strong>
                    <small>{{ $checkout['sepay_status'] === 'disabled' ? __('frontend.checkout.sepay_disabled') : __('frontend.checkout.sepay_unavailable') }}</small>
                  </span>
                </div>
              @endif
            </div>
            @if ($errors->has('payment_method'))
              <small class="field-error">{{ $errors->first('payment_method') }}</small>
            @endif
            <p class="field-hint">{{ __('frontend.checkout.payment_note') }}</p>
          </section>

          <div class="checkout-actions">
            <a class="btn btn--ghost" href="{{ route('cart') }}">{{ __('frontend.checkout.back_to_cart') }}</a>
            <button class="btn btn--indigo checkout-submit" type="submit" data-checkout-submit @disabled(! $checkout['can_submit'])>
              {{ __('frontend.checkout.submit') }}
            </button>
          </div>
          <p class="checkout-terms">{{ __('frontend.checkout.terms_note') }}</p>
        </form>

        <aside class="checkout-summary" aria-labelledby="checkout-summary-title">
          @include('frontend.checkout.partials.summary', ['cartPage' => $checkout['cart']])
        </aside>
      </div>
    </div>
  </section>
</main>
@endsection
