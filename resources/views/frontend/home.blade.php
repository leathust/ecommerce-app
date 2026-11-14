@extends('frontend.layout')

@section('content')
  <main id="main">
    @if ($homePage->heroProducts === [] && $homePage->trendingProducts === [] && $homePage->categories === [] && $homePage->heroBanners === [] && $homePage->discountBanners === [])
      <section class="home-empty" aria-labelledby="home-empty-title">
        <div class="container">
          <div class="home-empty__card">
            <h1 id="home-empty-title">{{ __('frontend.home.empty_title') }}</h1>
            <p>{{ __('frontend.home.empty_text') }}</p>
            <a href="{{ route('shop') }}" class="btn">{{ __('frontend.home.view_all_products') }}</a>
          </div>
        </div>
      </section>
    @else
      @include('frontend.home.hero', ['products' => $homePage->heroProducts, 'banners' => $homePage->heroBanners])
      @include('frontend.home.trending', ['products' => $homePage->trendingProducts, 'categories' => $homePage->categoryTabs])
      @include('frontend.home.discounts', ['categories' => $homePage->categories, 'banners' => $homePage->discountBanners])
      @include('frontend.home.categories', ['categories' => $homePage->categories])
      @include('frontend.home.suggestions', ['products' => $homePage->suggestedProducts])
      @include('frontend.home.brands', ['brands' => $homePage->brands])
    @endif

    @include('frontend.home.newsletter')
  </main>
@endsection
