  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="mark">
            @if($storefrontSettings['logo_url'])
              <img src="{{ $storefrontSettings['logo_url'] }}" alt="{{ $storefrontSettings['name'] }}" style="max-width:170px;max-height:48px;object-fit:contain">
            @else
              <span class="brand-mark">{{ $storefrontSettings['initial'] }}</span> {{ $storefrontSettings['name'] }}
            @endif
          </div>
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
        <span>© {{ now()->year }} {{ $storefrontSettings['name'] }} · All rights reserved</span>
        <span>Free HTML template · No framework · No build step</span>
      </div>
    </div>
  </footer>
