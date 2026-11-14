@extends('frontend.layout')

@section('title', __('frontend.cart.title'))
@section('robots', 'noindex,nofollow')

@section('content')
<main id="main">
  <section class="page-head">
    <div class="container">
      <div class="crumbs"><a href="{{ route('home') }}">{{ __('frontend.catalog.home') }}</a><span class="sep">›</span><span>{{ __('frontend.cart.title') }}</span></div>
      <h1>{{ __('frontend.cart.title') }}</h1>
      <p>{{ __('frontend.cart.intro') }}</p>
    </div>
  </section>
  <section class="section cart-page">
    <div class="container">
      <div data-cart-region data-cart-url="{{ route('cart') }}">
        @include('frontend.cart.partials.contents', ['cartPage' => $cartPage])
      </div>
    </div>
  </section>
</main>
@endsection
