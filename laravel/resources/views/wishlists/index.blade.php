@extends('layouts.app')

@section('title', 'Mis listas · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Mis listas</h1>
            <p class="bajada">Lo que pides tú. Quién te lo va a regalar no aparece por ningún lado.</p>
        </div>
        <a class="boton" href="{{ route('wishlists.create') }}">Nueva lista</a>
    </div>

    @forelse ($wishlists as $wishlist)
        {{-- `tarjeta-enlazada`: la tarjeta entera lleva a la lista. Antes lo
             único pulsable eran las dos palabras del título, un objetivo
             diminuto dentro de una caja grande. --}}
        <article class="tarjeta tarjeta-enlazada">
            <div class="fila">
                <div>
                    <p class="tarjeta-titulo">
                        <a href="{{ route('wishlists.show', $wishlist) }}">{{ $wishlist->name }}</a>
                    </p>
                    <p class="tarjeta-meta">
                        @if ($wishlist->items_count === 0)
                            {{-- Una lista vacía no es «0 regalos»: es una lista a
                                 la que le falta el siguiente paso, y decirlo así
                                 es lo que hace que se dé. --}}
                            Vacía todavía
                        @else
                            {{ $wishlist->items_count }} {{ Str::plural('regalo', $wishlist->items_count) }}
                        @endif
                        @if ($wishlist->event_date)
                            · {{ $wishlist->event_date->translatedFormat('d \d\e F Y') }}
                        @endif
                    </p>
                </div>
                <span class="etiqueta" title="{{ $wishlist->visibilityEnum()->description() }}">
                    {{ $wishlist->visibilityEnum()->title() }}
                </span>
            </div>
        </article>
    @empty
        <div class="vacio">
            <span class="vacio-icono" aria-hidden="true">🎁</span>
            <p>Todavía no tienes listas.</p>
            <p class="vacio-pista">
                Una lista es donde apuntas lo que te gustaría que te regalaran.
                Quien la abra puede reservar algo sin que tú te enteres.
            </p>
            <a class="boton" href="{{ route('wishlists.create') }}">Crear la primera</a>
        </div>
    @endforelse
@endsection
