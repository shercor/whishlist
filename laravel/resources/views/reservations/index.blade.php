@extends('layouts.app')

@section('title', 'Voy a regalar · whishlist')

@section('contenido')
    <h1>Voy a regalar</h1>
    <p class="bajada">Lo que tienes reservado. Nadie más ve esta pantalla, y menos los dueños de las listas.</p>

    @forelse ($reservations as $reservation)
        @php $item = $reservation->wishlistItem; @endphp

        <article class="tarjeta">
            <div class="fila">
                <div class="con-miniatura">
                    @if ($item->product->imageSrc())
                        <div class="marco-foto miniatura">
                            <img src="{{ $item->product->imageSrc() }}" alt="" loading="lazy">
                        </div>
                    @endif

                    <div>
                    <p class="tarjeta-titulo">
                        <button type="button" class="abre-detalle" data-abre-detalle="reserva-{{ $item->id }}">
                            {{ $item->displayName() }}
                        </button>
                    </p>
                    <p class="tarjeta-meta">
                        Para {{ $item->wishlist->user->publicName() }} · «{{ $item->wishlist->name }}»
                        @if ($reservation->expires_at)
                            <br>Se suelta sola el {{ $reservation->expires_at->translatedFormat('d \d\e F') }}
                            @if ($reservation->expires_at->isPast())
                                <span class="etiqueta etiqueta-espera">Plazo vencido</span>
                            @endif
                        @endif
                    </p>
                    </div>
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

            <x-producto-modal :id="'reserva-'.$item->id"
                              :producto="$item->product"
                              :titulo="$item->displayName()"
                              :notas="$item->notes"
                              :prioridad="$item->priorityEnum()"
                              :estado="\App\Enums\GiftState::RESERVED_BY_ME">
                <form method="POST" action="{{ route('reservations.destroy', $item) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="boton-plano boton-peligro">Soltar la reserva</button>
                </form>
            </x-producto-modal>
        </article>
    @empty
        <div class="vacio">
            <p>No tienes nada reservado.</p>
            <a class="boton" href="{{ route('discover') }}">Buscar a quién regalarle</a>
        </div>
    @endforelse
@endsection
