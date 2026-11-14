@extends('frontend.layout')

@section('content')
  <main id="main" data-catalog-shop data-shop-url="{{ route('shop') }}">
    <section class="page-head">
      <div class="container">
        <div class="crumbs"><a href="{{ route('home') }}">{{ __('frontend.catalog.home') }}</a> <span class="sep">›</span> <span>{{ __('frontend.catalog.shop') }}</span></div>
        <h1>{{ __('frontend.catalog.all_products') }}</h1>
        <p>{{ __('frontend.catalog.shop_intro') }}</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="shop-layout">
          <aside class="filters" aria-label="{{ __('frontend.catalog.filters') }}">
            <form method="GET" action="{{ route('shop') }}" class="catalog-filters" id="catalog-filters">
              <div class="filter-block">
                <label class="filter-title" for="catalog-q">{{ __('frontend.catalog.search') }}</label>
                <input id="catalog-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('frontend.catalog.search_placeholder') }}" autocomplete="off" />
              </div>
              <fieldset class="filter-block">
                <legend>{{ __('frontend.catalog.category') }}</legend>
                @foreach ($categories as $category)
                  <label><input type="checkbox" name="category[]" value="{{ $category['slug'] }}" @checked(in_array($category['slug'], $filters['category'], true)) /><span>{{ $category['name'] }}</span><span class="ct">{{ $category['count'] }}</span></label>
                @endforeach
              </fieldset>
              <fieldset class="filter-block price-filter" data-price-filter>
                <legend>{{ __('frontend.catalog.price_range') }}</legend>
                @php
                  $selectedMin = $filters['min_price'] ?? $priceBounds['min'];
                  $selectedMax = $filters['max_price'] ?? $priceBounds['max'];
                @endphp
                <div class="price-bounds"><span>{{ $moneyFormatter->format($priceBounds['min']) }}</span><span>{{ $moneyFormatter->format($priceBounds['max']) }}</span></div>
                <div class="dual-range">
                  <div class="dual-range__track" data-range-track></div>
                  <input type="range" name="min_price" min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" step="1000" value="{{ $selectedMin }}" aria-label="{{ __('frontend.catalog.minimum_price') }}" data-range-min />
                  <input type="range" name="max_price" min="{{ $priceBounds['min'] }}" max="{{ $priceBounds['max'] }}" step="1000" value="{{ $selectedMax }}" aria-label="{{ __('frontend.catalog.maximum_price') }}" data-range-max />
                </div>
                <div class="price-selected"><strong data-min-output>{{ $moneyFormatter->format($selectedMin) }}</strong><strong data-max-output>{{ $moneyFormatter->format($selectedMax) }}</strong></div>
              </fieldset>
              <fieldset class="filter-block">
                <legend>{{ __('frontend.catalog.brand') }}</legend>
                @foreach ($brands as $brand)
                  <label><input type="checkbox" name="brand[]" value="{{ $brand['slug'] }}" @checked(in_array($brand['slug'], $filters['brand'], true)) /><span>{{ $brand['name'] }}</span><span class="ct">{{ $brand['count'] }}</span></label>
                @endforeach
              </fieldset>
              <input type="hidden" name="sort" value="{{ $filters['sort'] }}" data-filter-sort />
              <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}" data-filter-per-page />
              <button class="btn btn--indigo btn--block" type="submit">{{ __('frontend.catalog.apply') }}</button>
              <a href="{{ route('shop') }}" class="catalog-reset" data-catalog-reset>{{ __('frontend.catalog.reset') }}</a>
            </form>
          </aside>
          <div class="catalog-content">
            <div id="catalog-results" aria-live="polite">@include('frontend.shop.results')</div>
            <div class="catalog-loading" data-catalog-loading hidden aria-hidden="true"><span class="catalog-spinner" aria-hidden="true"></span><span>{{ __('frontend.catalog.loading') }}</span></div>
            <p class="catalog-error" data-catalog-error hidden role="alert">{{ __('frontend.catalog.load_error') }}</p>
          </div>
        </div>
      </div>
    </section>
  </main>
@endsection
