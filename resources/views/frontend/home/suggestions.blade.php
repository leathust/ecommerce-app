<section class="section" style="padding-top:0" aria-labelledby="suggestions-title">
  <div class="container">
    <div class="section-head">
      <h2 id="suggestions-title">{{ __('frontend.home.suggestions') }}</h2>
      <a href="{{ route('shop') }}" class="view-all">{{ __('frontend.home.more_picks') }} <span aria-hidden="true">→</span></a>
    </div>
    @if ($products !== [])
      <div class="compact-row">
        @foreach ($products as $product)
          <article class="compact-card">
            <a href="{{ $product['url'] }}" class="pic"><img src="{{ $product['image_url'] }}" alt="{{ $product['image_alt'] }}" loading="lazy" /></a>
            <div>
              <div class="stock">{{ __('frontend.home.in_stock', ['count' => $product['quantity']]) }}</div>
              <a href="{{ $product['url'] }}" class="name">{{ $product['name'] }}</a>
              <div class="price">{{ $product['formatted_price'] }}</div>
              <a href="{{ $product['url'] }}" class="btn">{{ __('frontend.home.view_details') }}</a>
              @include('frontend.components.cart-add', ['product' => $product, 'variant' => 'compact'])
            </div>
          </article>
        @endforeach
      </div>
    @else
      <p class="home-block-empty">{{ __('frontend.home.empty_block') }}</p>
    @endif
  </div>
</section>
