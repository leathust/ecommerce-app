@if ($banners !== [] || $categories !== [])
  <section class="section" style="padding-top:0" aria-label="{{ __('frontend.home.promotion_eyebrow') }}">
    <div class="container">
      <div class="discount-row">
        @foreach ($banners !== [] ? $banners : array_slice($categories, 0, 2) as $category)
          @php($isBanner = $banners !== [])
          <article class="discount-card discount-card--{{ $isBanner ? ($category['theme'] ?? 'watch') : ($loop->first ? 'watch' : 'airpods') }}">
            <span class="meta">{{ $isBanner ? (($category['eyebrow'] ?? '') ?: __('frontend.home.promotion_eyebrow')) : __('frontend.home.promotion_eyebrow') }}</span>
            <h3>{{ $isBanner ? $category['title'] : __('frontend.home.promotion_title', ['category' => $category['name']]) }}</h3>
            @if ($category['url'])<a href="{{ $category['url'] }}" class="shop-now">{{ ($isBanner && $category['button'] !== '') ? $category['button'] : __('frontend.home.shop_now') }} <span aria-hidden="true">→</span></a>@endif
            <img class="product" src="{{ $category['image_url'] }}" alt="{{ $isBanner ? $category['title'] : $category['name'] }}" loading="lazy" />
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endif
