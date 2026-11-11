@if($item['children'])
  <details class="drawer-menu__group drawer-menu__group--level-{{ $level }}" data-menu-depth="{{ $level }}" @if($item['active']) open @endif>
    <summary>{{ $item['label'] }}</summary>
    <div class="drawer-menu__children">
      @if($item['href'])
        <a href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if($item['rel']) rel="{{ $item['rel'] }}" @endif @if($item['self_active']) aria-current="page" @endif>{{ __('Xem tất cả') }} {{ $item['label'] }}</a>
      @endif
      @foreach($item['children'] as $child)
        @include('frontend.includes.menu-mobile-item', ['item' => $child, 'level' => $level + 1])
      @endforeach
    </div>
  </details>
@elseif($item['href'])
  <a class="drawer-menu__link drawer-menu__link--level-{{ $level }}" data-menu-depth="{{ $level }}" href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if($item['rel']) rel="{{ $item['rel'] }}" @endif @if($item['self_active']) aria-current="page" @endif>{{ $item['label'] }}</a>
@else
  <span class="drawer-menu__label drawer-menu__label--level-{{ $level }}" data-menu-depth="{{ $level }}">{{ $item['label'] }}</span>
@endif
