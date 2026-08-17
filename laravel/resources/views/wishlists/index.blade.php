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
        <article class="tarjeta">
            <div class="fila">
                <div>
                    <p class="tarjeta-titulo">
                        <a href="{{ route('wishlists.show', $wishlist) }}">{{ $wishlist->name }}</a>
                    </p>
                    <p class="tarjeta-meta">
                        {{ $wishlist->items_count }} {{ Str::plural('regalo', $wishlist->items_count) }}
                        @if ($wishlist->event_date)
                            · {{ $wishlist->event_date->translatedFormat('d \d\e F Y') }}
                        @endif
                    </p>
                </div>
                <span class="etiqueta">{{ $wishlist->visibilityEnum()->title() }}</span>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>Todavía no tienes listas.</p>
            <a class="boton" href="{{ route('wishlists.create') }}">Crear la primera</a>
        </div>
    @endforelse
@endsection
