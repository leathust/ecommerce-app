<ul class="main-nav" data-menu-render="desktop" data-menu-fallback="{{ $storefrontMenus['fallback']['header'] ? 'true' : 'false' }}" aria-label="{{ __('Điều hướng chính') }}">
  @foreach($storefrontMenus['header'] as $item)
    @include('frontend.includes.menu-desktop-item', ['item' => $item, 'level' => 1])
  @endforeach
</ul>
