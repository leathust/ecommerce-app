<!doctype html>
<html lang="{{ $storefrontSettings['locale'] }}">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  @php
    $seoDefaults = $storefrontSettings['seo'];
    $pageTitle = trim($__env->yieldContent('title', $seoDefaults['title']));
    $pageDescription = trim($__env->yieldContent('meta_description', $seoDefaults['description']));
    $defaultCanonical = url()->current();
    $pageCanonical = trim($__env->yieldContent('canonical', $defaultCanonical));
    $canonicalScheme = strtolower((string)parse_url($pageCanonical, PHP_URL_SCHEME));
    if (!filter_var($pageCanonical, FILTER_VALIDATE_URL) || !in_array($canonicalScheme, ['http', 'https'], true)) {
      $pageCanonical = $defaultCanonical;
    }
    $pageOgImage = trim($__env->yieldContent('og_image', (string)($seoDefaults['og_image_url'] ?? '')));
    if ($pageOgImage !== '' && str_starts_with($pageOgImage, '/')) {
      $pageOgImage = url($pageOgImage);
    }
    $ogImageScheme = strtolower((string)parse_url($pageOgImage, PHP_URL_SCHEME));
    if ($pageOgImage !== '' && (!filter_var($pageOgImage, FILTER_VALIDATE_URL) || !in_array($ogImageScheme, ['http', 'https'], true))) {
      $pageOgImage = '';
    }
    $requestedRobots = trim($__env->yieldContent('robots', 'index,follow'));
    $pageRobots = $seoDefaults['indexing_enabled'] && in_array($requestedRobots, ['index,follow', 'noindex,nofollow'], true)
      ? $requestedRobots
      : 'noindex,nofollow';
  @endphp
  <title>{{ $pageTitle }}</title>
  @if($pageDescription)<meta name="description" content="{{ $pageDescription }}" />@endif
  <link rel="canonical" href="{{ $pageCanonical }}" />
  <meta name="robots" content="{{ $pageRobots }}" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="{{ $pageTitle }}" />
  @if($pageDescription)<meta property="og:description" content="{{ $pageDescription }}" />@endif
  <meta property="og:url" content="{{ $pageCanonical }}" />
  @if($pageOgImage)<meta property="og:image" content="{{ $pageOgImage }}" />@endif
  @if($storefrontSettings['favicon_url'])<link rel="icon" href="{{ $storefrontSettings['favicon_url'] }}" />@endif
  <meta name="theme-color" content="#4F46E5" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Outfit:wght@400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" />
  <link rel="stylesheet" href="{{ asset('assets/frontend/css/styles.css') }}?v={{ time() }}" />
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <!-- Top utility strip -->
  <div class="utility">
    <div class="container">
      <span class="promo">
        <span class="tag">SALE</span>
        Free shipping on orders over $50 · 30-day returns
      </span>
      <span class="links">
        <a href="{{ Route('track-order') }}">Track order</a>
        <a href="{{ Route('help') }}">Help</a>
        <a href="{{ Route('language') }}">EN · USD</a>
      </span>
    </div>
  </div>

  @include('frontend.includes.header')

  <!-- Category nav -->
  <nav class="nav-bar" aria-label="Primary">
    <div class="container">
      <a href="{{ Route('shop') }}" class="all-cats">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        All Categories
      </a>
      @include('frontend.includes.menu-desktop')
      <span class="nav-cta">UP TO <strong>60% OFF</strong> ALL ITEMS</span>
    </div>
  </nav>

  <!-- Mobile drawer -->
  <div class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-head">
      <a href="{{ Route('home') }}" class="brand">
        @if($storefrontSettings['logo_url'])
          <img src="{{ $storefrontSettings['logo_url'] }}" alt="{{ $storefrontSettings['name'] }}" style="max-width:160px;max-height:42px;object-fit:contain">
        @else
          <span class="brand-mark">{{ $storefrontSettings['initial'] }}</span> {{ $storefrontSettings['name'] }}
        @endif
      </a>
      <button class="drawer-close" aria-label="Close menu">Close ✕</button>
    </div>
    @include('frontend.includes.menu-mobile')
    <a href="{{ Route('cart') }}" class="btn btn--indigo" style="margin-top:var(--s5); justify-content:center">View cart →</a>
  </div>


  @yield('content')


  @include('frontend.includes.footer')
  <script src="{{ asset('assets/frontend/js/main.js') }}?v={{ time() }}" defer></script>
</body>
</html>
