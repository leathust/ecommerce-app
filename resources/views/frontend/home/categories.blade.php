@if ($categories !== [])
  <section class="section" style="padding-top: var(--s5)" aria-labelledby="categories-title">
    <div class="container">
      <div class="section-head">
        <h2 id="categories-title">{{ __('frontend.home.shop_by_category') }}</h2>
        <a href="{{ route('shop') }}" class="view-all">{{ __('frontend.home.view_all_products') }} <span aria-hidden="true">→</span></a>
      </div>
      <div class="cats-grid">
        @foreach ($categories as $category)
          <a href="{{ $category['url'] }}" class="cat-tile">
            <div class="pic"><img src="{{ $category['image_url'] }}" alt="{{ $category['name'] }}" loading="lazy" /></div>
            <div class="name">{{ $category['name'] }}</div>
            <div class="count">{{ trans_choice('frontend.home.products_count', $category['product_count'], ['count' => $category['product_count']]) }}</div>
          </a>
        @endforeach
      </div>
    </div>
  </section>
@endif
