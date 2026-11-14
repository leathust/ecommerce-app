<section class="section home-products" style="padding-top: var(--s5)" aria-labelledby="trending-title" data-trending-tabs>
  <div class="container">
    <div class="section-head">
      <h2 id="trending-title">{{ __('frontend.home.trending') }}</h2>
      <a href="{{ route('shop') }}" class="view-all">{{ __('frontend.home.view_all') }} <span aria-hidden="true">→</span></a>
    </div>
    @if ($categories !== [])
      <nav class="tabs trending-tabs" role="tablist" aria-label="{{ __('frontend.home.shop_by_category') }}">
        @foreach ($categories as $category)
          <a
            id="trending-tab-{{ $category['id'] }}"
            class="tab {{ $loop->first ? 'is-active' : '' }}"
            href="{{ $category['url'] }}"
            role="tab"
            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
            aria-controls="trending-panel"
            tabindex="{{ $loop->first ? '0' : '-1' }}"
            data-trending-url="{{ route('home.trending', ['category' => $category['slug']]) }}"
          >{{ $category['name'] }}</a>
        @endforeach
      </nav>
    @endif

    <div
      id="trending-panel"
      class="trending-panel"
      role="tabpanel"
      tabindex="0"
      aria-live="polite"
      aria-busy="false"
      @if ($categories !== []) aria-labelledby="trending-tab-{{ $categories[0]['id'] }}" @endif
    >
      <div data-trending-results>
        @include('frontend.home.trending-products', ['products' => $products])
      </div>
      <div class="trending-loading" data-trending-loading hidden>
        <span class="catalog-spinner" aria-hidden="true"></span>
        <span>{{ __('frontend.home.trending_loading') }}</span>
      </div>
    </div>
    <p class="trending-error" data-trending-error role="alert" hidden>{{ __('frontend.home.trending_error') }}</p>
  </div>
</section>
