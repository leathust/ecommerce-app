@extends('frontend.layout')

@section('title', __('frontend.wishlist.title'))
@section('robots', 'noindex,nofollow')

@section('content')
<main id="main">
  <section class="page-head">
    <div class="container">
      <div class="crumbs">
        <a href="{{ route('home') }}">{{ __('frontend.catalog.home') }}</a>
        <span class="sep">›</span>
        <span>{{ __('frontend.wishlist.title') }}</span>
      </div>
      <h1>{{ __('frontend.wishlist.title') }}</h1>
      <p>{{ __('frontend.wishlist.intro') }}</p>
    </div>
  </section>

  <section class="section wishlist-page">
    <div class="container">
      @if (session('wishlist_success'))
        <div class="form-alert form-alert--success" role="status">{{ session('wishlist_success') }}</div>
      @endif

      @if ($products->isNotEmpty())
        <div class="wishlist-toolbar">
          <p>{{ trans_choice('frontend.wishlist.products_count', $products->total(), ['count' => $products->total()]) }}</p>
          <a href="{{ route('shop') }}" class="btn btn--ghost btn--sm">{{ __('frontend.wishlist.continue_shopping') }}</a>
        </div>

        <div class="products wishlist-grid">
          @foreach ($products as $product)
            <div class="wishlist-card">
              @include('frontend.components.product-card', ['product' => $product, 'hideWishlistToggle' => true])
              <form method="POST" action="{{ route('wishlist.destroy', ['product' => $product['id']]) }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="return_to" value="{{ $products->url($products->currentPage()) }}">
                <button type="submit" class="wishlist-remove">
                  <span aria-hidden="true">×</span>
                  {{ __('frontend.wishlist.remove') }}
                </button>
              </form>
            </div>
          @endforeach
        </div>

        @include('frontend.components.catalog-pagination', ['paginator' => $products])
      @else
        <div class="wishlist-empty">
          <span class="wishlist-empty__icon" aria-hidden="true">♡</span>
          <h2>{{ __('frontend.wishlist.empty_title') }}</h2>
          <p>{{ __('frontend.wishlist.empty_text') }}</p>
          <a href="{{ route('shop') }}" class="btn btn--indigo">{{ __('frontend.wishlist.shop_now') }}</a>
        </div>
      @endif
    </div>
  </section>
</main>
@endsection
