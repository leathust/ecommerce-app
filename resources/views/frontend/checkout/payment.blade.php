@extends('frontend.layout')

@section('title', __('frontend.payment.page_title'))
@section('robots', 'noindex,nofollow')

@push('head')
  @if (in_array($payment['state'], ['waiting', 'manual_review'], true))
    <noscript><meta http-equiv="refresh" content="15"></noscript>
  @endif
@endpush

@section('content')
<main id="main" class="payment-page">
  <section class="page-head">
    <div class="container">
      <div class="crumbs">
        <a href="{{ route('home') }}">{{ __('frontend.catalog.home') }}</a><span class="sep">›</span>
        <span>{{ __('frontend.payment.page_title') }}</span>
      </div>
      <h1>{{ __('frontend.payment.page_title') }}</h1>
      <p>{{ __('frontend.payment.page_intro') }}</p>
    </div>
  </section>

  <section class="section payment-section">
    <div class="container">
      @if (in_array($payment['state'], ['waiting', 'manual_review'], true))
        <div class="payment-layout"
          data-payment-status
          data-state="{{ $payment['state'] }}"
          data-payment-public-id="{{ $payment['public_id'] }}"
          data-broadcast-channel="{{ $payment['broadcast_channel'] }}"
          data-broadcast-auth-url="{{ $payment['broadcast_auth_url'] }}"
          data-status-url="{{ $payment['status_url'] }}"
          data-poll-after-ms="{{ $payment['poll_after_ms'] }}"
          data-remaining-seconds="{{ $payment['remaining_seconds'] }}"
          data-error-text="{{ __('frontend.payment.poll_error') }}"
          data-lost-text="{{ __('frontend.payment.session_lost') }}"
          data-copy-failed-text="{{ __('frontend.payment.copy_failed') }}">
          <div class="payment-card">
            <header class="payment-card__head">
              <span class="eyebrow">{{ __('frontend.payment.eyebrow') }}</span>
              <h2>{{ $payment['state'] === 'waiting' ? __('frontend.payment.amount_label') : __('frontend.payment.review_title') }}</h2>
              <p class="payment-amount">{{ $payment['formatted_amount'] }}</p>
              <p class="payment-order">{{ __('frontend.payment.order_code') }}: <strong>{{ $payment['order_code'] }}</strong></p>
            </header>

            @if ($payment['state'] === 'waiting')
              <div class="payment-qr">
                @if ($payment['qr_url'])
                  <img src="{{ $payment['qr_url'] }}" width="260" height="260" referrerpolicy="no-referrer"
                    alt="{{ __('frontend.payment.qr_alt', ['amount' => $payment['formatted_amount'], 'code' => $payment['payment_code']]) }}">
                @else
                  <p class="form-alert form-alert--error" role="alert">{{ __('frontend.payment.qr_unavailable') }}</p>
                @endif
                <p class="payment-scan-hint">{{ __('frontend.payment.scan_hint') }}</p>
              </div>

              <dl class="payment-details">
                <div>
                  <dt>{{ __('frontend.payment.bank_label') }}</dt>
                  <dd>{{ $payment['bank_code'] }}</dd>
                </div>
                <div>
                  <dt>{{ __('frontend.payment.account_label') }}</dt>
                  <dd>
                    <span>{{ $payment['bank_account_number'] }}</span>
                    <button type="button" class="payment-copy" data-copy-value="{{ $payment['bank_account_number'] }}" data-copied-text="{{ __('frontend.payment.copied') }}">{{ __('frontend.payment.copy') }}</button>
                  </dd>
                </div>
                @if ($payment['bank_account_name'])
                  <div>
                    <dt>{{ __('frontend.payment.holder_label') }}</dt>
                    <dd>{{ $payment['bank_account_name'] }}</dd>
                  </div>
                @endif
                <div>
                  <dt>{{ __('frontend.payment.content_label') }}</dt>
                  <dd>
                    <code>{{ $payment['payment_code'] }}</code>
                    <button type="button" class="payment-copy" data-copy-value="{{ $payment['payment_code'] }}" data-copied-text="{{ __('frontend.payment.copied') }}">{{ __('frontend.payment.copy') }}</button>
                  </dd>
                </div>
                <div>
                  <dt>{{ __('frontend.payment.amount_label') }}</dt>
                  <dd>{{ $payment['formatted_amount'] }}</dd>
                </div>
              </dl>

              <p class="payment-warning">{{ __('frontend.payment.content_warning') }}</p>

              <div class="payment-countdown" data-payment-countdown-wrap>
                <span class="payment-countdown__label">{{ __('frontend.payment.countdown_label') }}</span>
                <strong data-payment-countdown role="timer" aria-live="off">{{ sprintf('%02d:%02d', intdiv($payment['remaining_seconds'], 60), $payment['remaining_seconds'] % 60) }}</strong>
                <span class="payment-countdown__hint">{{ __('frontend.payment.countdown_hint') }}</span>
              </div>
            @else
              <div class="payment-review">
                <p>{{ __('frontend.payment.manual_review_text') }}</p>
                <dl class="payment-details">
                  <div>
                    <dt>{{ __('frontend.payment.content_label') }}</dt>
                    <dd><code>{{ $payment['payment_code'] }}</code></dd>
                  </div>
                  <div>
                    <dt>{{ __('frontend.payment.amount_label') }}</dt>
                    <dd>{{ $payment['formatted_amount'] }}</dd>
                  </div>
                </dl>
                <p class="payment-warning">{{ __('frontend.payment.manual_review_warning') }}</p>
              </div>
            @endif

            <div class="payment-status" role="status" aria-live="polite" aria-atomic="true">
              <strong data-payment-status-text>{{ $payment['state'] === 'waiting' ? __('frontend.payment.status_waiting') : __('frontend.payment.status_manual_review') }}</strong>
              <p data-payment-status-detail>{{ $payment['state'] === 'waiting' ? __('frontend.payment.status_waiting_hint') : __('frontend.payment.status_manual_review_hint') }}</p>
            </div>

            <div class="payment-actions">
              <a class="btn btn--ghost" href="{{ $payment['refresh_url'] }}" data-payment-refresh>{{ __('frontend.payment.check_again') }}</a>
              <a class="btn btn--ghost" href="{{ route('contact') }}">{{ __('frontend.payment.contact_support') }}</a>
            </div>

            <div class="payment-feedback" data-payment-feedback role="status" aria-live="polite" aria-atomic="true" hidden></div>
            <p class="payment-support">{{ __('frontend.payment.support_note') }}</p>
          </div>

          <aside class="payment-help" aria-label="{{ __('frontend.payment.help_title') }}">
            <section class="payment-help__card">
              <h2>{{ __('frontend.payment.help_title') }}</h2>
              <ol class="payment-help__steps">
                <li>{{ __('frontend.payment.help_step_1') }}</li>
                <li>{{ __('frontend.payment.help_step_2') }}</li>
                <li>{{ __('frontend.payment.help_step_3') }}</li>
              </ol>
            </section>
            <section class="payment-help__card">
              <h3>{{ __('frontend.payment.help_note_title') }}</h3>
              <p>{{ __('frontend.payment.help_note') }}</p>
            </section>
          </aside>
        </div>
      @else
        @php($state = $payment['state'])
        <div class="payment-result payment-result--{{ $state }}" data-payment-focus tabindex="-1">
          <span class="payment-result__icon" aria-hidden="true">{{ $state === 'paid' ? '✓' : '!' }}</span>
          <p class="eyebrow">{{ $payment['order_code'] }}</p>
          <h2>{{ __('frontend.payment.'.$state.'_title') }}</h2>
          <p class="payment-result__text">{{ __('frontend.payment.'.$state.'_text') }}</p>

          <dl class="payment-details payment-result__details">
            <div>
              <dt>{{ __('frontend.payment.amount_label') }}</dt>
              <dd>{{ $payment['formatted_amount'] }}</dd>
            </div>
            <div>
              <dt>{{ __('frontend.payment.order_code') }}</dt>
              <dd>{{ $payment['order_code'] }}</dd>
            </div>
            @if ($state === 'paid' && $payment['paid_at_label'])
              <div>
                <dt>{{ __('frontend.payment.paid_at_label') }}</dt>
                <dd>{{ $payment['paid_at_label'] }}</dd>
              </div>
            @endif
            @if ($payment['payment_code'])
              <div>
                <dt>{{ __('frontend.payment.content_label') }}</dt>
                <dd><code>{{ $payment['payment_code'] }}</code></dd>
              </div>
            @endif
          </dl>

          @if ($state === 'expired')
            <p class="payment-warning">{{ __('frontend.payment.expired_late_note') }}</p>
          @endif

          <div class="payment-result__actions">
            <a class="btn btn--indigo" href="{{ route('shop') }}">{{ __('frontend.payment.continue_shopping') }}</a>
            @if (in_array($state, ['expired', 'cancelled', 'failed'], true))
              <a class="btn btn--ghost" href="{{ route('contact') }}">{{ __('frontend.payment.contact_support') }}</a>
            @endif
          </div>
        </div>
      @endif
    </div>
  </section>
</main>
@endsection

@push('scripts')
  @vite('resources/js/payment-realtime.js')
@endpush
