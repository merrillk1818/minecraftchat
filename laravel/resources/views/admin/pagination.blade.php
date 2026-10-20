@if($paginator->hasPages())
  <div class="pagination">
    @if($paginator->onFirstPage())
      <span class="page-btn disabled pixel">«</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" class="page-btn pixel">«</a>
    @endif

    @foreach($elements as $element)
      @if(is_string($element))
        <span class="page-btn disabled pixel">…</span>
      @endif
      @if(is_array($element))
        @foreach($element as $page => $url)
          @if($page == $paginator->currentPage())
            <span class="page-btn active pixel">{{ $page }}</span>
          @else
            <a href="{{ $url }}" class="page-btn pixel">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" class="page-btn pixel">»</a>
    @else
      <span class="page-btn disabled pixel">»</span>
    @endif
  </div>
@endif
