<li class="footer-menu__item footer-menu__item--level-{{ $level }}" data-menu-depth="{{ $level }}">
  @if($item['href'])
    <a href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if($item['rel']) rel="{{ $item['rel'] }}" @endif @if($item['self_active']) aria-current="page" @endif>{{ $item['label'] }}</a>
  @else
    <span>{{ $item['label'] }}</span>
  @endif
  @if($item['children'])
    <ul class="footer-menu__list footer-menu__list--level-{{ $level + 1 }}">
      @foreach($item['children'] as $child)
        @include('frontend.includes.menu-footer-item', ['item' => $child, 'level' => $level + 1])
      @endforeach
    </ul>
  @endif
</li>
