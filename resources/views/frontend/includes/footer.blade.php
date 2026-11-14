  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="{{ Route('home') }}" class="mark">
            {{-- <div class="mark"> --}}
              @if($storefrontSettings['logo_url'])
                <img src="{{ $storefrontSettings['logo_url'] }}" alt="">
              @else
                <span class="brand-mark" aria-hidden="true">{{ $storefrontSettings['initial'] }}</span>
              @endif
              <span class="footer-brand__name">{{ $storefrontSettings['name'] }}</span>
            {{-- </div> --}}
          </a>
          @if($storefrontSettings['tagline'])<p>{{ $storefrontSettings['tagline'] }}</p>@endif
          @if($storefrontSettings['social'])
            <div class="socials" style="margin-top:var(--s4)">
              @foreach($storefrontSettings['social'] as $platform => $socialUrl)
                <a href="{{ $socialUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($platform) }}">
                  <span aria-hidden="true">{{ match($platform) { 'facebook' => 'f', 'instagram' => '◎', 'youtube' => '▶', 'tiktok' => '♪', default => '↗' } }}</span>
                </a>
              @endforeach
            </div>
          @endif
        </div>
        @include('frontend.includes.menu-footer')
      </div>
      <div class="footer-bottom">
        <span>{{ __('frontend.footer.copyright', ['year' => now()->year, 'name' => $storefrontSettings['name']]) }}</span>
      </div>
    </div>
  </footer>
