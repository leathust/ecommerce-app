@if ($products !== [])
  <div class="products">
    @foreach ($products as $product)
      @include('frontend.components.product-card', ['product' => $product])
    @endforeach
  </div>
@else
  <p class="home-block-empty">{{ __('frontend.home.empty_block') }}</p>
@endif
