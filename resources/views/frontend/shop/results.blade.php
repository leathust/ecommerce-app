<div class="shop-toolbar">
  <span class="count">{{ __('frontend.catalog.showing', ['from' => $products->firstItem() ?? 0, 'to' => $products->lastItem() ?? 0, 'total' => $products->total()]) }}</span>
  <form method="GET" action="{{ route('shop') }}" class="catalog-sort" data-catalog-sort-form>
    @if ($filters['q'] !== '')<input type="hidden" name="q" value="{{ $filters['q'] }}" />@endif
    @foreach ($filters['category'] as $slug)<input type="hidden" name="category[]" value="{{ $slug }}" />@endforeach
    @foreach ($filters['brand'] as $slug)<input type="hidden" name="brand[]" value="{{ $slug }}" />@endforeach
    @if ($filters['min_price'] !== null)<input type="hidden" name="min_price" value="{{ $filters['min_price'] }}" />@endif
    @if ($filters['max_price'] !== null)<input type="hidden" name="max_price" value="{{ $filters['max_price'] }}" />@endif
    <label for="catalog-sort">{{ __('frontend.catalog.sort') }}</label>
    <select id="catalog-sort" name="sort" data-catalog-sort>
      @foreach (['newest', 'price_asc', 'price_desc', 'name'] as $sort)<option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ __('frontend.catalog.'.$sort) }}</option>@endforeach
    </select>
    <label for="catalog-per-page">{{ __('frontend.catalog.per_page') }}</label>
    <select id="catalog-per-page" name="per_page" data-catalog-per-page>
      @foreach ([12, 24, 48] as $size)<option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>@endforeach
    </select>
    <noscript><button class="btn" type="submit">{{ __('frontend.catalog.apply') }}</button></noscript>
  </form>
</div>
@if ($products->isNotEmpty())
  <div class="shop-grid">
    @foreach ($products as $product) @include('frontend.components.product-card', ['product' => $product]) @endforeach
  </div>
  @include('frontend.components.catalog-pagination', ['paginator' => $products])
@else
  <div class="catalog-empty">{{ __('frontend.catalog.empty') }}</div>
@endif
