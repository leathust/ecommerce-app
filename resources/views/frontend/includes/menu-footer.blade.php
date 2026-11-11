<div class="footer-menu-contents" data-menu-render="footer" data-menu-fallback="{{ $storefrontMenus['fallback']['footer'] ? 'true' : 'false' }}">
  @foreach($storefrontMenus['footer'] as $item)
    <div class="footer-col">
      <h4>@if($item['href'])<a href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if($item['rel']) rel="{{ $item['rel'] }}" @endif @if($item['self_active']) aria-current="page" @endif>{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif</h4>
      @if($item['children'])
        <ul class="footer-menu__list footer-menu__list--level-2">
          @foreach($item['children'] as $child)
            @include('frontend.includes.menu-footer-item', ['item' => $child, 'level' => 2])
          @endforeach
        </ul>
      @endif
    </div>
  @endforeach
</div>
