@php($hasUnavailable = collect($cartPage['items'])->contains(fn (array $item): bool => ! $item['available']))
<h2 id="checkout-summary-title">{{ __('frontend.checkout.summary_title') }}</h2>
<p class="checkout-summary__count">{{ trans_choice('frontend.cart.products_count', $cartPage['available_quantity'], ['count' => $cartPage['available_quantity']]) }}</p>
<ul class="checkout-summary__items">
  @foreach ($cartPage['items'] as $item)
    <li class="{{ $item['available'] ? '' : 'is-unavailable' }}">
      <img src="{{ $item['image_url'] }}" alt="{{ $item['image_alt'] }}" width="52" height="52" loading="lazy">
      <div>
        <span class="name">{{ $item['name'] }}</span>
        <small>{{ __('frontend.cart.quantity') }}: {{ $item['cart_quantity'] }}</small>
        @unless ($item['available'])
          <small class="field-error">{{ __('frontend.cart.availability_'.$item['availability_key']) }}</small>
        @endunless
      </div>
      <strong>{{ $item['formatted_line_subtotal'] }}</strong>
    </li>
  @endforeach
</ul>

<div class="cart-line">
  <span>{{ trans_choice('frontend.cart.subtotal_items', $cartPage['available_quantity'], ['count' => $cartPage['available_quantity']]) }}</span>
  <strong>{{ $cartPage['formatted_subtotal'] }}</strong>
</div>
<div class="cart-line">
  <span>{{ __('frontend.cart.shipping_estimate') }}</span>
  <strong>{{ $cartPage['shipping_is_free'] ? __('frontend.cart.free') : $cartPage['formatted_estimated_shipping'] }}</strong>
</div>
<div class="cart-line is-total">
  <span>{{ __('frontend.checkout.total_label') }}</span>
  <strong>{{ $cartPage['formatted_estimated_total'] }}</strong>
</div>

@if ($hasUnavailable)
  <p class="checkout-summary__notice checkout-summary__notice--warning">{{ __('frontend.checkout.stock_changed') }}</p>
@endif
@if ($cartPage['free_shipping_enabled'] && ! $cartPage['shipping_is_free'] && $cartPage['available_quantity'] > 0)
  <p class="checkout-summary__notice">{{ __('frontend.cart.free_shipping_remaining', ['amount' => $cartPage['free_shipping_remaining']]) }}</p>
@endif
@if ($cartPage['minimum_order_enabled'] && ! $cartPage['minimum_order_met'])
  <p class="checkout-summary__notice checkout-summary__notice--warning">{{ __('frontend.cart.minimum_remaining', ['amount' => $cartPage['minimum_order_remaining']]) }}</p>
@endif
<p class="cart-summary__disclaimer">{{ __('frontend.checkout.summary_disclaimer') }}</p>
