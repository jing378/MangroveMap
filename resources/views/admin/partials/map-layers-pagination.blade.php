@if($paginator->hasPages())
<div class="pagination">
    @if($paginator->onFirstPage())
    <span class="pagination-btn disabled">← Previous</span>
    @else
    <a class="pagination-btn" href="{{ $paginator->previousPageUrl() }}">← Previous</a>
    @endif
    <div class="pagination-info">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} ({{ $paginator->total() }} {{ \Illuminate\Support\Str::plural($noun, $paginator->total()) }})</div>
    @if($paginator->hasMorePages())
    <a class="pagination-btn" href="{{ $paginator->nextPageUrl() }}">Next →</a>
    @else
    <span class="pagination-btn disabled">Next →</span>
    @endif
</div>
@else
<div class="pagination-info pagination-total">{{ $paginator->total() }} {{ \Illuminate\Support\Str::plural($noun, $paginator->total()) }}</div>
@endif
