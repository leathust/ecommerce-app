<article class="product-card">
  @unless ($hideWishlistToggle ?? false)
    @include('frontend.components.wishlist-toggle', ['product' => $product, 'variant' => 'card'])
  @endunless
  <a href="{{ $product['url'] }}" class="img-wrap" aria-label="{{ $product['name'] }}">
    @if ($product['badge'])
      <span class="badge">{{ __('frontend.home.'.$product['badge']) }}</span>
    @endif
    <img src="{{ $product['image_url'] }}" alt="{{ $product['image_alt'] }}" loading="lazy" />
  </a>
  <div class="stock stock--{{ $product['stock_status'] }}">
    <span class="dot"></span>
    {{ $product['stock_status'] === 'in_stock'
        ? __('frontend.home.in_stock', ['count' => $product['quantity']])
        : __('frontend.home.out_of_stock') }}
  </div>
  <a href="{{ $product['url'] }}" class="name">{{ $product['name'] }}</a>
  <div class="price"><span class="now">{{ $product['formatted_price'] }}</span></div>
  <div class="product-card__actions">
    <a href="{{ $product['url'] }}" class="btn">{{ __('frontend.home.view_details') }}</a>
    @include('frontend.components.cart-add', ['product' => $product, 'variant' => 'card'])
  </div>
</article>
