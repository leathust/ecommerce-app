@php
  $wishlistProductId = (int) $product['id'];
  $wishlistActive = in_array($wishlistProductId, $storefrontWishlistIds ?? [], true);
  $wishlistReturnTo = request()->fullUrl();
  $wishlistLabel = $wishlistActive
    ? __('frontend.wishlist.remove_named', ['name' => $product['name']])
    : __('frontend.wishlist.add_named', ['name' => $product['name']]);
  $wishlistStoreUrl = route('wishlist.store', ['storefrontProduct' => $product['slug']]);
  $wishlistDestroyUrl = route('wishlist.destroy', ['product' => $wishlistProductId]);
@endphp

@if ($storefrontIsCustomer ?? false)
  <form
    method="POST"
    action="{{ $wishlistActive ? $wishlistDestroyUrl : $wishlistStoreUrl }}"
    class="wishlist-toggle-form wishlist-toggle-form--{{ $variant ?? 'card' }}"
    data-wishlist-form
    data-product-id="{{ $wishlistProductId }}"
    data-store-url="{{ $wishlistStoreUrl }}"
    data-destroy-url="{{ $wishlistDestroyUrl }}"
    data-login-url="{{ route('login', ['return_to' => $wishlistReturnTo]) }}"
    data-add-label="{{ __('frontend.wishlist.add_named', ['name' => $product['name']]) }}"
    data-remove-label="{{ __('frontend.wishlist.remove_named', ['name' => $product['name']]) }}"
    data-add-text="{{ __('frontend.wishlist.add') }}"
    data-remove-text="{{ __('frontend.wishlist.remove') }}"
  >
    @csrf
    @if ($wishlistActive) @method('DELETE') @endif
    <input type="hidden" name="return_to" value="{{ $wishlistReturnTo }}">
    <button
      type="submit"
      class="wishlist-toggle {{ $wishlistActive ? 'is-active' : '' }}"
      aria-label="{{ $wishlistLabel }}"
      aria-pressed="{{ $wishlistActive ? 'true' : 'false' }}"
      title="{{ $wishlistLabel }}"
    >
      <svg width="20" height="20" viewBox="0 0 24 24" fill="{{ $wishlistActive ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      @if (($variant ?? 'card') === 'detail')
        <span data-wishlist-label>{{ $wishlistActive ? __('frontend.wishlist.remove') : __('frontend.wishlist.add') }}</span>
      @else
        <span class="sr-only" data-wishlist-label>{{ $wishlistLabel }}</span>
      @endif
    </button>
  </form>
@else
  <a
    href="{{ route('login', ['return_to' => $wishlistReturnTo]) }}"
    class="wishlist-toggle wishlist-toggle--{{ $variant ?? 'card' }}"
    aria-label="{{ __('frontend.wishlist.login_to_add_named', ['name' => $product['name']]) }}"
    title="{{ __('frontend.wishlist.login_to_add') }}"
  >
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
    @if (($variant ?? 'card') === 'detail')
      <span>{{ __('frontend.wishlist.login_to_add') }}</span>
    @else
      <span class="sr-only">{{ __('frontend.wishlist.login_to_add') }}</span>
    @endif
  </a>
@endif
