@php
  $variant = in_array($variant ?? 'desktop', ['desktop', 'mobile'], true) ? $variant : 'desktop';
  $currentLocale = in_array(app()->getLocale(), ['vi', 'en'], true) ? app()->getLocale() : 'vi';
  $locales = [
    'vi' => ['name' => 'Tiếng Việt', 'short' => 'VI'],
    'en' => ['name' => 'English', 'short' => 'EN'],
  ];
  $switcherId = 'language-menu-'.$variant;
@endphp

<div class="language-switcher language-switcher--{{ $variant }}" data-language-switcher>
  <button
    class="language-switcher__trigger"
    type="button"
    aria-expanded="false"
    aria-controls="{{ $switcherId }}"
    aria-haspopup="menu"
    data-language-trigger
  >
    <span class="language-flag" aria-hidden="true">
      @if($currentLocale === 'vi')
        <svg viewBox="0 0 30 20" focusable="false"><rect width="30" height="20" rx="2" fill="#DA251D"/><path d="m15 4 1.41 4.34h4.56l-3.69 2.68 1.41 4.34L15 12.68l-3.69 2.68 1.41-4.34-3.69-2.68h4.56L15 4Z" fill="#FFDE00"/></svg>
      @else
        <svg viewBox="0 0 30 20" focusable="false"><rect width="30" height="20" rx="2" fill="#21468B"/><path d="M0 0 30 20M30 0 0 20" stroke="#fff" stroke-width="4"/><path d="M0 0 30 20M30 0 0 20" stroke="#C8102E" stroke-width="2"/><path d="M15 0v20M0 10h30" stroke="#fff" stroke-width="6"/><path d="M15 0v20M0 10h30" stroke="#C8102E" stroke-width="3"/></svg>
      @endif
    </span>
    <span class="language-switcher__current">
      <span class="language-switcher__full-name">{{ $locales[$currentLocale]['name'] }}</span>
      <span class="language-switcher__short-name">{{ $locales[$currentLocale]['short'] }}</span>
    </span>
    <svg class="language-switcher__chevron" width="14" height="8" viewBox="0 0 14 8" fill="none" aria-hidden="true"><path d="m1 1 6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </button>

  <div class="language-switcher__menu" id="{{ $switcherId }}" role="menu" data-language-menu hidden>
    @foreach($locales as $locale => $metadata)
      <form action="{{ route('language.update') }}" method="POST" role="none">
        @csrf
        <input type="hidden" name="locale" value="{{ $locale }}">
        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
        <button
          class="language-switcher__option"
          type="submit"
          role="menuitem"
          @if($locale === $currentLocale) aria-current="true" disabled @endif
        >
          <span class="language-flag" aria-hidden="true">
            @if($locale === 'vi')
              <svg viewBox="0 0 30 20" focusable="false"><rect width="30" height="20" rx="2" fill="#DA251D"/><path d="m15 4 1.41 4.34h4.56l-3.69 2.68 1.41 4.34L15 12.68l-3.69 2.68 1.41-4.34-3.69-2.68h4.56L15 4Z" fill="#FFDE00"/></svg>
            @else
              <svg viewBox="0 0 30 20" focusable="false"><rect width="30" height="20" rx="2" fill="#21468B"/><path d="M0 0 30 20M30 0 0 20" stroke="#fff" stroke-width="4"/><path d="M0 0 30 20M30 0 0 20" stroke="#C8102E" stroke-width="2"/><path d="M15 0v20M0 10h30" stroke="#fff" stroke-width="6"/><path d="M15 0v20M0 10h30" stroke="#C8102E" stroke-width="3"/></svg>
            @endif
          </span>
          <span>{{ $metadata['name'] }}</span>
          @if($locale === $currentLocale)<span class="language-switcher__check" aria-hidden="true">✓</span>@endif
        </button>
      </form>
    @endforeach
  </div>

  <noscript>
    <form class="language-switcher__fallback" action="{{ route('language.update') }}" method="POST">
      @csrf
      <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
      <label for="language-fallback-{{ $variant }}">Language</label>
      <select id="language-fallback-{{ $variant }}" name="locale">
        @foreach($locales as $locale => $metadata)
          <option value="{{ $locale }}" @selected($locale === $currentLocale)>{{ $metadata['name'] }}</option>
        @endforeach
      </select>
      <button type="submit">Apply</button>
    </form>
  </noscript>
</div>
