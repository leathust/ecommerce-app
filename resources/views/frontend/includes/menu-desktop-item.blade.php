@php($submenuId = 'desktop-submenu-'.$item['id'])
<li class="main-nav__item main-nav__item--level-{{ $level }} {{ $item['active'] ? 'is-active' : '' }}" data-menu-item data-menu-depth="{{ $level }}">
  <div class="main-nav__entry">
    @if($item['href'])
      <a class="main-nav__link" href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if($item['rel']) rel="{{ $item['rel'] }}" @endif @if($item['self_active']) aria-current="page" @endif>{{ $item['label'] }}</a>
      @if($item['children'])
        <button class="main-nav__toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="{{ $submenuId }}" aria-label="{{ __('Mở menu con của') }} {{ $item['label'] }}"><span aria-hidden="true">›</span></button>
      @endif
    @elseif($item['children'])
      <button class="main-nav__link main-nav__link--toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="{{ $submenuId }}">{{ $item['label'] }} <span aria-hidden="true">›</span></button>
    @else
      <span class="main-nav__link">{{ $item['label'] }}</span>
    @endif
  </div>
  @if($item['children'])
    <ul class="main-nav__submenu" id="{{ $submenuId }}" data-menu-submenu data-menu-depth="{{ $level + 1 }}" aria-label="{{ $item['label'] }}">
      @foreach($item['children'] as $child)
        @include('frontend.includes.menu-desktop-item', ['item' => $child, 'level' => $level + 1])
      @endforeach
    </ul>
  @endif
</li>
