@if(empty($payload))
  <p class="text-muted mb-0">Không có dữ liệu.</p>
@else
  <div class="border rounded-3 overflow-hidden">
    <dl class="mb-0">
      @foreach($payload as $key => $value)
        <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
          <dt class="small text-uppercase text-muted mb-1">{{ $key }}</dt>
          <dd class="mb-0">
            @if(is_array($value))
              @include('admin.access-logs.partials.payload', ['payload' => $value])
            @elseif(is_bool($value))
              <span class="badge {{ $value ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $value ? 'true' : 'false' }}</span>
            @elseif(is_null($value))
              <span class="text-muted">null</span>
            @else
              <span class="text-break">{{ $value }}</span>
            @endif
          </dd>
        </div>
      @endforeach
    </dl>
  </div>
@endif
