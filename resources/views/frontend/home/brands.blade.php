@if ($brands !== [])
  <section class="brands" aria-label="Brands">
    <div class="container">
      <div class="brand-row">
        @foreach ($brands as $brand)
          <a href="{{ $brand['url'] }}" class="brand-logo" aria-label="{{ $brand['name'] }}">
            @if ($brand['image_url'])
              <img src="{{ $brand['image_url'] }}" alt="{{ $brand['name'] }}" loading="lazy" />
            @else
              {{ $brand['name'] }}
            @endif
          </a>
        @endforeach
      </div>
    </div>
  </section>
@endif
