@if ($paginator->hasPages())
    <nav class="admin-pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())<span class="muted">← Prev</span>@else<a href="{{ $paginator->previousPageUrl() }}">← Prev</a>@endif
        <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Next →</a>@else<span class="muted">Next →</span>@endif
    </nav>
@endif
