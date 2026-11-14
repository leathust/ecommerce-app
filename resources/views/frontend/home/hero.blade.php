@php($heroItems = $banners !== [] ? $banners : $products)
@if ($heroItems !== [])
  @php($themes = ['bento-card--lg', 'bento-card--purple', 'bento-card--teal', 'bento-card--orange', 'bento-card--green', 'bento-card--black'])
  <section class="hero" aria-label="{{ __('frontend.home.featured') }}">
    <div class="container">
      <div class="bento bento--count-{{ count($heroItems) }}">
        @foreach ($heroItems as $product)
          @if ($loop->index === 3)<div class="bento-row">@endif
          @php($isBanner = $banners !== [])
          @php($themeClass = $isBanner ? 'bento-card--'.($product['theme'] ?? 'purple') : $themes[$loop->index])
          <article class="bento-card {{ $loop->first ? 'bento-card--lg' : $themeClass }}">
            <div class="sparkle"></div>
            <div class="bento-card__content">
              <span class="eyebrow">
                {{ $isBanner ? (($product['eyebrow'] ?? '') ?: __('frontend.home.featured')) : (($product['category_name'] ?? '') ?: __('frontend.home.featured')) }}
                @if (! $isBanner && $product['is_featured']) · {{ __('frontend.home.featured') }} @endif
              </span>
              @if ($loop->first)
                <h2>{{ $isBanner ? $product['title'] : $product['name'] }}</h2>
                @if ($product['description'] !== '')<p>{{ $product['description'] }}</p>@endif
                @if ($product['url'])<a href="{{ $product['url'] }}" class="btn btn--paper">{{ ($isBanner && $product['button'] !== '') ? $product['button'] : __('frontend.home.shop_now') }} <span aria-hidden="true">→</span></a>@endif
              @else
                <h3>{{ $isBanner ? $product['title'] : $product['name'] }}</h3>
                @if ($product['url'])<a href="{{ $product['url'] }}" class="shop-now">{{ ($isBanner && $product['button'] !== '') ? $product['button'] : __('frontend.home.shop_now') }} <span aria-hidden="true">→</span></a>@endif
              @endif
            </div>
            <img class="product" src="{{ $product['image_url'] }}" alt="{{ $isBanner ? $product['title'] : $product['image_alt'] }}" @unless($loop->first) loading="lazy" @endunless />
          </article>
          @if ($loop->last && $loop->index >= 3)</div>@endif
        @endforeach
      </div>
    </div>
  </section>
@endif
