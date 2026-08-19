@extends('layouts.app')

@section('title', 'Descubrir · whishlist')

@section('contenido')
    <h1>Listas públicas</h1>
    <p class="bajada">Lo que otros están pidiendo. Entra y reserva algo sin que se enteren.</p>

    <div class="rejilla">
        @forelse ($wishlists as $wishlist)
            <article class="tarjeta tarjeta-enlazada">
                {{-- `titulo-recortado` y no dejar que crezca: un nombre de tres
                     líneas estiraba su fila entera de la rejilla y dejaba a las
                     tarjetas de al lado con un hueco abajo. El nombre completo
                     sigue estando en el title. --}}
                <p class="tarjeta-titulo titulo-recortado" title="{{ $wishlist->name }}">
                    <a href="{{ route('gifts.show', $wishlist) }}">{{ $wishlist->name }}</a>
                </p>

                {{-- `al-pie` empuja al autor abajo del todo, así todas las
                     tarjetas de una fila alinean su pie mida lo que mida el
                     título de encima. --}}
                <div class="tarjeta-meta persona al-pie">
                    <x-avatar :usuario="$wishlist->user" tamano="chico" />
                    <div>
                        {{-- El arroba y no el nombre: es lo que se puede buscar,
                             y lo que hay que dictarle a alguien para llegar a
                             esta persona. --}}
                        <span class="arroba-tenue">{{ $wishlist->user->handle() }}</span>
                        <span class="linea-tenue">
                            {{ $wishlist->items_count }} {{ Str::plural('regalo', $wishlist->items_count) }}
                            @if ($wishlist->event_date)
                                · {{ $wishlist->event_date->translatedFormat('d \d\e M') }}
                            @endif
                        </span>
                    </div>
                </div>
            </article>
        @empty
            <div class="vacio">
                <span class="vacio-icono" aria-hidden="true">🔍</span>
                <p>Todavía no hay listas públicas de otra gente.</p>
                <p class="vacio-pista">
                    Las listas públicas aparecen acá solas. Mientras tanto, puedes
                    buscar a alguien por su arroba.
                </p>
                <a class="boton-plano" href="{{ route('users.search') }}">Buscar personas</a>
            </div>
        @endforelse
    </div>

    {{ $wishlists->links('pagination.simple') }}
@endsection
