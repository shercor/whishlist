@extends('layouts.app')

@section('title', 'Descubrir · whishlist')

@section('contenido')
    <h1>Listas públicas</h1>
    <p class="bajada">Lo que otros están pidiendo. Entra y reserva algo sin que se enteren.</p>

    <div class="rejilla">
        @forelse ($wishlists as $wishlist)
            <article class="tarjeta">
                <p class="tarjeta-titulo">
                    <a href="{{ route('gifts.show', $wishlist) }}">{{ $wishlist->name }}</a>
                </p>
                <p class="tarjeta-meta">
                    {{ $wishlist->user->publicName() }} ·
                    {{ $wishlist->items_count }} {{ Str::plural('regalo', $wishlist->items_count) }}
                    @if ($wishlist->event_date)
                        <br>{{ $wishlist->event_date->translatedFormat('d \d\e F Y') }}
                    @endif
                </p>
            </article>
        @empty
            <div class="vacio">
                <p>Todavía no hay listas públicas de otra gente.</p>
            </div>
        @endforelse
    </div>

    {{ $wishlists->links('pagination.simple') }}
@endsection
