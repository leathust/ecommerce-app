@if ($paginator->hasPages())
  <nav class="pagination" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="pg is-disabled" aria-disabled="true">‹</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" class="pg" rel="prev" aria-label="{{ __('frontend.catalog.previous') }}">‹</a>
    @endif
    @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
      @if ($page === $paginator->currentPage())
        <span class="pg is-active" aria-current="page">{{ $page }}</span>
      @else
        <a href="{{ $url }}" class="pg">{{ $page }}</a>
      @endif
    @endforeach
    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" class="pg" rel="next" aria-label="{{ __('frontend.catalog.next') }}">›</a>
    @else
      <span class="pg is-disabled" aria-disabled="true">›</span>
    @endif
  </nav>
@endif
