<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sprylo — Tech &amp; Gadgets Ecommerce HTML Template</title>
  <meta name="description" content="Sprylo is a free modern HTML template for tech and electronics stores — multi-color bento hero, indigo primary palette, Plus Jakarta + Outfit + Roboto Mono pair, 5 fully responsive pages, no framework, no build step." />
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
      <div class="main-nav">
        <a href="{{ Route('home') }}" {{ Route::is('home') ? "aria-current='page'" : '' }}>Home</a>
        <a href="{{ Route('shop') }}" {{ Route::is('shop') ? "aria-current='page'" : '' }}>Shop ▾</a>
        <a href="{{ Route('products') }}" {{ Route::is('products') ? "aria-current='page'" : '' }}>Products</a>
        <a href="{{ Route('cart') }}" {{ Route::is('cart') ? "aria-current='page'" : '' }}>Cart</a>
        <a href="{{ Route('contact') }}" {{ Route::is('contact') ? "aria-current='page'" : '' }}>Contact</a>
      </div>
      <span class="nav-cta">UP TO <strong>60% OFF</strong> ALL ITEMS</span>
    </div>
  </nav>

  <!-- Mobile drawer -->
  <div class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-head">
      <a href="{{ Route('home') }}" class="brand"><span class="brand-mark">S</span> Sprylo</a>
      <button class="drawer-close" aria-label="Close menu">Close ✕</button>
    </div>
    <a href="{{ Route('home') }}">Home</a>
    <a href="{{ Route('shop') }}">Shop</a>
    <a href="{{ Route('products') }}">Products</a>
    <a href="{{ Route('cart') }}">Cart</a>
    <a href="{{ Route('contact') }}">Contact</a>
    <a href="{{ Route('cart') }}" class="btn btn--indigo" style="margin-top:var(--s5); justify-content:center">View cart →</a>
  </div>


  @yield('content')


  @include('frontend.includes.footer')
  <script src="{{ asset('assets/frontend/js/main.js') }}?v={{ time() }}" defer></script>
</body>
</html>
