@php
  $cartVariant = $variant ?? 'card';
  $cartAvailable = ($product['stock_status'] ?? 'out_of_stock') === 'in_stock' && ($product['quantity'] ?? 0) > 0;
  $cartReturnTo = request()->ajax()
    ? (request()->headers->get('referer') ?: route('home'))
    : request()->fullUrl();
@endphp

@if ($cartAvailable)
  <form
    class="cart-add cart-add--{{ $cartVariant }}"
    method="POST"
    action="{{ route('cart.items.store', ['storefrontProduct' => $product['slug']]) }}"
    data-cart-add-form
    data-product-id="{{ $product['id'] }}"
    data-pending-text="{{ __('frontend.cart.adding') }}"
  >
    @csrf
    <input type="hidden" name="return_to" value="{{ $cartReturnTo }}">
    @if ($cartVariant === 'detail')
      <label for="cart-add-quantity-{{ $product['id'] }}">{{ __('frontend.cart.quantity') }}</label>
      <div class="cart-add__detail-row">
        <input id="cart-add-quantity-{{ $product['id'] }}" name="quantity" type="number" inputmode="numeric" min="1" max="{{ min(99, $product['quantity']) }}" value="1" required>
        <button type="submit" class="btn btn--indigo" data-cart-submit>{{ __('frontend.cart.add') }}</button>
      </div>
    @else
      <input type="hidden" name="quantity" value="1">
      <button type="submit" class="btn cart-add__button" data-cart-submit aria-label="{{ __('frontend.cart.add_named', ['name' => $product['name']]) }}">
        <span aria-hidden="true">＋</span> {{ __('frontend.cart.add_short') }}
      </button>
    @endif
  </form>
@else
  <div class="cart-add cart-add--{{ $cartVariant }} cart-add--disabled">
    <button type="button" class="btn" disabled aria-disabled="true">{{ __('frontend.cart.out_of_stock') }}</button>
  </div>
@endif
