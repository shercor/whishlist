@extends('layouts.app')

@section('title', 'Regalarle a '.$wishlist->user->name.' · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>{{ $wishlist->name }}</h1>
            <p class="bajada">
                La lista de {{ $wishlist->user->name }}.
                @if ($wishlist->event_date)
                    Para el {{ $wishlist->event_date->translatedFormat('d \d\e F \d\e Y') }}.
                @endif
            </p>
        </div>
    </div>

    <p class="aviso aviso-ok">
        {{ $wishlist->user->name }} no ve nada de esta pantalla: ni qué está reservado, ni por quién.
    </p>

    @forelse ($items as $item)
        @php
            $reservado = $item->reserved_count > 0;
            $esMio = $item->mine_count > 0;
        @endphp

        <article @class(['tarjeta', 'recibido' => $item->isReceived()])>
            <div class="fila">
                <div>
                    <p class="tarjeta-titulo">{{ $item->displayName() }}</p>
                    <p class="tarjeta-meta">
                        {{ $item->product->category?->name }}
                        @if ($item->product->reference_price)
                            · ${{ number_format($item->product->reference_price, 0, ',', '.') }}
                        @endif
                        @if ($item->notes) · {{ $item->notes }} @endif
                    </p>
                    @if ($item->product->url)
                        <p class="tarjeta-meta"><a href="{{ $item->product->url }}" rel="noopener" target="_blank">Dónde se consigue</a></p>
                    @endif
                </div>

                <div class="fila-acciones">
                    <span @class(['etiqueta', 'etiqueta-alta' => $item->priorityEnum() === \App\Enums\ItemPriority::HIGH])>
                        {{ $item->priorityEnum()->label() }}
                    </span>

                    @if ($item->isReceived())
                        <span class="etiqueta">ya lo tiene</span>
                    @elseif ($esMio)
                        {{-- Solo a quien reservó se le dice que fue él. --}}
                        <span class="etiqueta etiqueta-ok">lo reservaste tú</span>
                        <form method="POST" action="{{ route('reservations.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="boton-plano">Soltar</button>
                        </form>
                    @elseif ($reservado)
                        {{-- Tomado, y hasta ahí. Quién lo tomó no se dice nunca. --}}
                        <span class="etiqueta etiqueta-espera">ya lo reservaron</span>
                    @else
                        <form method="POST" action="{{ route('reservations.store', $item) }}">
                            @csrf
                            <button type="submit" class="boton">Lo regalo yo</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>{{ $wishlist->user->name }} todavía no ha puesto nada en esta lista.</p>
        </div>
    @endforelse
@endsection
