<nav class="drawer-menu" aria-label="{{ __('Điều hướng di động') }}" data-menu-render="mobile" data-menu-fallback="{{ $storefrontMenus['fallback']['header'] ? 'true' : 'false' }}">
  @foreach($storefrontMenus['header'] as $item)
    @include('frontend.includes.menu-mobile-item', ['item' => $item, 'level' => 1])
  @endforeach
</nav>
