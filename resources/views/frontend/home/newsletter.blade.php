<section id="newsletter" style="background: var(--paper)" aria-labelledby="newsletter-title">
  <div class="container">
    <div class="newsletter">
      <div class="newsletter-grid">
        <div>
          <h2 id="newsletter-title">{{ __('frontend.home.newsletter_title') }}</h2>
          <p>{{ __('frontend.home.newsletter_text') }}</p>
        </div>
        <form class="newsletter-form-wrap" action="{{ route('newsletter.subscribe') }}" method="POST" data-newsletter-form
          data-error-message="{{ __('frontend.home.newsletter_error') }}" novalidate>
          @csrf
          <div class="newsletter-form">
            <label class="sr-only" for="newsletter-email">{{ __('frontend.home.newsletter_email') }}</label>
            <input id="newsletter-email" name="email" type="email" maxlength="255" autocomplete="email"
              value="{{ old('email') }}" placeholder="{{ __('frontend.home.newsletter_placeholder') }}" required>
            <button class="btn" type="submit" data-newsletter-submit>{{ __('frontend.home.newsletter_submit') }}</button>
          </div>
          <label class="newsletter-consent">
            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
            <span>{{ __('frontend.home.newsletter_consent') }}</span>
          </label>
          <div class="newsletter-honeypot" aria-hidden="true">
            <label for="newsletter-website">Website</label>
            <input id="newsletter-website" name="website" type="text" tabindex="-1" autocomplete="off">
          </div>
          <p class="newsletter-feedback @if($errors->hasAny(['email', 'consent', 'website'])) is-error @endif"
            data-newsletter-feedback role="status" aria-live="polite">
            @if(session('newsletter_success'))
              {{ session('newsletter_success') }}
            @elseif($errors->hasAny(['email', 'consent', 'website']))
              {{ $errors->first('email') ?: ($errors->first('consent') ?: $errors->first('website')) }}
            @endif
          </p>
        </form>
      </div>
    </div>
  </div>
</section>
