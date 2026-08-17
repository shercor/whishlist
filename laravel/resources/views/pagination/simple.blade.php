@if ($paginator->hasPages())
    {{-- Paginador propio: el que trae Laravel viene con clases de Tailwind y
         acá no hay Tailwind. --}}
    <nav class="acciones" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="boton-plano" aria-disabled="true">Anterior</span>
        @else
            <a class="boton-plano" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif

        <span class="tarjeta-meta">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="boton-plano" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
        @else
            <span class="boton-plano" aria-disabled="true">Siguiente</span>
        @endif
    </nav>
@endif
