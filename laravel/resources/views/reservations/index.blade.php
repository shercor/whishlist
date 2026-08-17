@extends('layouts.app')

@section('title', 'Voy a regalar · whishlist')

@section('contenido')
    <h1>Voy a regalar</h1>
    <p class="bajada">Lo que tienes reservado. Nadie más ve esta pantalla, y menos los dueños de las listas.</p>

    @forelse ($reservations as $reservation)
        @php $item = $reservation->wishlistItem; @endphp

        <article class="tarjeta">
            <div class="fila">
                <div>
                    <p class="tarjeta-titulo">{{ $item->displayName() }}</p>
                    <p class="tarjeta-meta">
                        Para {{ $item->wishlist->user->name }} · «{{ $item->wishlist->name }}»
                        @if ($reservation->expires_at)
                            <br>Se suelta sola el {{ $reservation->expires_at->translatedFormat('d \d\e F') }}
                            @if ($reservation->expires_at->isPast())
                                <span class="etiqueta etiqueta-espera">Plazo vencido</span>
                            @endif
                        @endif
                    </p>
                </div>
                <div class="fila-acciones">
                    <a class="boton-plano" href="{{ route('gifts.show', $item->wishlist) }}">Ver la lista</a>
                    <form method="POST" action="{{ route('reservations.destroy', $item) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="boton-plano boton-peligro">Soltar</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>No tienes nada reservado.</p>
            <a class="boton" href="{{ route('discover') }}">Buscar a quién regalarle</a>
        </div>
    @endforelse
@endsection
