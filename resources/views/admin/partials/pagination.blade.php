@if ($paginator->hasPages())
    <div class="pagination-wrap">
        @if ($paginator->onFirstPage())
            <span class="admin-btn admin-btn--ghost" style="opacity:.4; pointer-events:none;">← Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="admin-btn admin-btn--ghost">← Anterior</a>
        @endif
        <span class="td-muted" style="font-family:var(--font-mono); font-size:var(--text-xs);">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="admin-btn admin-btn--ghost">Próxima →</a>
        @else
            <span class="admin-btn admin-btn--ghost" style="opacity:.4; pointer-events:none;">Próxima →</span>
        @endif
    </div>
@endif
