@if (session('cart_success'))<div class="form-alert form-alert--success" role="status">{{ session('cart_success') }}</div>@endif
@if ($errors->any())<div class="form-alert form-alert--error" role="alert">{{ $errors->first() }}</div>@endif

@if (! $cartPage['has_items'])
  <div class="cart-empty" data-cart-focus tabindex="-1">
    <span class="cart-empty__icon" aria-hidden="true">🛒</span><h2>{{ __('frontend.cart.empty_title') }}</h2><p>{{ __('frontend.cart.empty_text') }}</p>
    <a href="{{ route('shop') }}" class="btn btn--indigo">{{ __('frontend.cart.continue_shopping') }}</a>
  </div>
@else
  <div class="cart-toolbar" data-cart-focus tabindex="-1">
    <p>{{ trans_choice('frontend.cart.products_count', $cartPage['available_quantity'], ['count' => $cartPage['available_quantity']]) }}</p>
    <div class="cart-toolbar__actions">
      <a href="{{ route('shop') }}" class="btn btn--ghost btn--sm">{{ __('frontend.cart.continue_shopping') }}</a>
      <form method="POST" action="{{ route('cart.clear') }}" data-cart-mutation-form data-cart-action="clear">@csrf @method('DELETE')<input type="hidden" name="return_to" value="{{ route('cart') }}"><button type="submit" class="btn btn--ghost btn--sm" data-cart-mutation-submit>{{ __('frontend.cart.clear') }}</button></form>
    </div>
  </div>
  <div class="cart-layout">
    <div class="cart-list" aria-label="{{ __('frontend.cart.items_label') }}">
      @foreach ($cartPage['items'] as $item)
        <article class="cart-row{{ $item['available'] ? '' : ' cart-row--unavailable' }}" data-cart-row="{{ $item['id'] }}">
          <div class="pic"><img src="{{ $item['image_url'] }}" alt="{{ $item['image_alt'] }}"></div>
          <div class="info">
            @if ($item['available'])<a class="name" href="{{ $item['url'] }}">{{ $item['name'] }}</a>@else<span class="name">{{ $item['name'] }}</span>@endif
            <div class="variant">{{ __('frontend.catalog.sku') }}: {{ $item['sku'] }} @if ($item['category_name']) · {{ $item['category_name'] }} @endif</div>
            <div class="cart-unit-price">{{ __('frontend.cart.unit_price') }}: {{ $item['formatted_price'] }}</div>
            @unless ($item['available'])<span class="cart-availability cart-availability--unavailable">{{ __('frontend.cart.availability_'.$item['availability_key']) }}</span>@endunless
          </div>
          @if ($item['available'])
            <form class="cart-quantity-form" method="POST" action="{{ route('cart.items.update', ['product' => $item['id']]) }}" data-cart-mutation-form data-cart-action="update" data-product-id="{{ $item['id'] }}">
              @csrf @method('PATCH')<input type="hidden" name="return_to" value="{{ route('cart') }}"><label for="cart-quantity-{{ $item['id'] }}">{{ __('frontend.cart.quantity') }}</label>
              <div class="cart-quantity-control"><button type="button" class="cart-quantity-step" data-cart-step="-1" aria-label="{{ __('frontend.cart.decrease_quantity', ['name' => $item['name']]) }}">−</button><input id="cart-quantity-{{ $item['id'] }}" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $item['max_quantity'] }}" value="{{ $item['cart_quantity'] }}" required><button type="button" class="cart-quantity-step" data-cart-step="1" aria-label="{{ __('frontend.cart.increase_quantity', ['name' => $item['name']]) }}">+</button><button type="submit" class="btn btn--ghost btn--sm" data-cart-mutation-submit>{{ __('frontend.cart.update') }}</button></div>
            </form>
          @else
            <div class="cart-quantity-static"><span>{{ __('frontend.cart.quantity') }}</span><strong>{{ $item['cart_quantity'] }}</strong></div>
          @endif
          <div class="cart-row__subtotal"><span>{{ __('frontend.cart.line_subtotal') }}</span><strong>{{ $item['formatted_line_subtotal'] }}</strong>@unless ($item['available'])<small>{{ __('frontend.cart.not_counted') }}</small>@endunless</div>
          <form class="cart-remove-form" method="POST" action="{{ route('cart.items.destroy', ['product' => $item['id']]) }}" data-cart-mutation-form data-cart-action="remove" data-product-id="{{ $item['id'] }}">@csrf @method('DELETE')<input type="hidden" name="return_to" value="{{ route('cart') }}"><button type="submit" class="cart-remove" data-cart-mutation-submit aria-label="{{ __('frontend.cart.remove_named', ['name' => $item['name']]) }}"><span aria-hidden="true">×</span><span>{{ __('frontend.cart.remove') }}</span></button></form>
        </article>
      @endforeach
    </div>
    <aside class="cart-summary" aria-labelledby="cart-summary-title">
      <h2 id="cart-summary-title">{{ __('frontend.cart.summary') }}</h2>
      <div class="cart-line"><span>{{ trans_choice('frontend.cart.subtotal_items', $cartPage['available_quantity'], ['count' => $cartPage['available_quantity']]) }}</span><strong>{{ $cartPage['formatted_subtotal'] }}</strong></div>
      <div class="cart-line"><span>{{ __('frontend.cart.shipping_estimate') }}</span><strong>{{ $cartPage['shipping_is_free'] ? __('frontend.cart.free') : $cartPage['formatted_estimated_shipping'] }}</strong></div>
      <div class="cart-line is-total"><span>{{ __('frontend.cart.estimated_total') }}</span><strong>{{ $cartPage['formatted_estimated_total'] }}</strong></div>
      @if ($cartPage['free_shipping_enabled'] && ! $cartPage['shipping_is_free'] && $cartPage['available_quantity'] > 0)<p class="cart-summary__notice">{{ __('frontend.cart.free_shipping_remaining', ['amount' => $cartPage['free_shipping_remaining']]) }}</p>@endif
      @if ($cartPage['minimum_order_enabled'] && ! $cartPage['minimum_order_met'])<p class="cart-summary__notice cart-summary__notice--warning">{{ __('frontend.cart.minimum_remaining', ['amount' => $cartPage['minimum_order_remaining']]) }}</p>@endif
      <p class="cart-summary__disclaimer">{{ __('frontend.cart.estimate_disclaimer') }}</p><span class="btn btn--indigo btn--block is-disabled" aria-disabled="true">{{ __('frontend.cart.checkout_later') }}</span>
    </aside>
  </div>
@endif
