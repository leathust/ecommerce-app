@extends('frontend.layout')

@section('content')
  <main id="main">
    <div class="container">
      <div class="crumbs" style="padding-top: var(--s5)">
        <a href="{{ route('home') }}">{{ __('frontend.catalog.home') }}</a>
        <span class="sep">›</span>
        <a href="{{ route('shop') }}">{{ __('frontend.catalog.shop') }}</a>
        <span class="sep">›</span>
        <span>{{ $product['name'] }}</span>
      </div>

      <section class="product-detail">
        <div class="gallery">
          @if (count($product['images']) > 1)
            <div class="gallery-thumbs">
              @foreach ($product['images'] as $image)
                <a href="{{ $image['url'] }}" class="{{ $loop->first ? 'is-active' : '' }}" aria-label="{{ __('frontend.catalog.image_of', ['position' => $loop->iteration, 'name' => $product['name']]) }}">
                  <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" />
                </a>
              @endforeach
            </div>
          @endif
          <figure class="gallery-main">
            <img src="{{ $product['images'][0]['url'] }}" alt="{{ $product['images'][0]['alt'] }}" />
          </figure>
        </div>

        <div class="pdp-info">
          <span class="pdp-cat">{{ $product['category_name'] }}@if($product['brand_name']) · {{ $product['brand_name'] }}@endif</span>
          <h1>{{ $product['name'] }}</h1>
          <div class="catalog-meta">
            <span>{{ __('frontend.catalog.sku') }}: <strong>{{ $product['sku'] }}</strong></span>
            <span class="stock stock--{{ $product['stock_status'] }}">{{ $product['stock_status'] === 'in_stock' ? __('frontend.home.in_stock', ['count' => $product['quantity']]) : __('frontend.home.out_of_stock') }}</span>
          </div>
          @if ($product['description'] !== '')
            <h2 class="pdp-subtitle">{{ __('frontend.catalog.description') }}</h2>
            <p class="desc">{{ $product['description'] }}</p>
          @endif
          <div class="price-row"><span class="now">{{ $product['formatted_price'] }}</span></div>
          <div class="pdp-wishlist">
            @include('frontend.components.wishlist-toggle', ['product' => $product, 'variant' => 'detail'])
          </div>
          <div class="pdp-cart">
            @include('frontend.components.cart-add', ['product' => $product, 'variant' => 'detail'])
          </div>
        </div>
      </section>

      @if ($relatedProducts !== [])
        <section class="section">
          <div class="section-head">
            <h2>{{ __('frontend.catalog.related') }}</h2>
            <a href="{{ route('shop', ['category' => $product['category_slug']]) }}" class="view-all">{{ __('frontend.home.view_all_products') }} →</a>
          </div>
          <div class="products">
            @foreach ($relatedProducts as $related)
              @include('frontend.components.product-card', ['product' => $related])
            @endforeach
          </div>
        </section>
      @endif
    </div>
  </main>
@endsection
