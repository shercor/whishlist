@extends('layouts.app')

@section('title', 'Regalarle a '.$wishlist->user->publicName().' · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>{{ $wishlist->name }}</h1>
            <p class="bajada">
                La lista de {{ $wishlist->user->publicName() }}.
                @if ($wishlist->event_date)
                    Para el {{ $wishlist->event_date->translatedFormat('d \d\e F \d\e Y') }}.
                @endif
            </p>
        </div>
    </div>

    <p class="aviso aviso-ok">
        {{ $wishlist->user->publicName() }} no ve nada de esta pantalla: ni qué está reservado, ni por quién.
    </p>

    @forelse ($items as $item)
        @php
            $estado = \App\Enums\GiftState::forViewer($item);
        @endphp

        <article @class(['tarjeta', 'recibido' => $item->isReceived()])>
            <div class="fila">
                <div class="con-miniatura">
                    @if ($item->product->imageSrc())
                        <div class="marco-foto miniatura">
                            <img src="{{ $item->product->imageSrc() }}" alt="" loading="lazy">
                        </div>
                    @endif

                    <div>
                        <p class="tarjeta-titulo">
                            <button type="button" class="abre-detalle" data-abre-detalle="regalo-{{ $item->id }}">
                                {{ $item->displayName() }}
                            </button>
                        </p>
                        <p class="tarjeta-meta">
                            {{ $item->product->category?->name }}
                            @if ($item->product->reference_price)
                                · ${{ number_format($item->product->reference_price, 0, ',', '.') }} aprox.
                            @endif
                            @if ($item->notes) · {{ $item->notes }} @endif
                        </p>
                        @if ($item->product->url)
                            <p class="tarjeta-meta"><a href="{{ $item->product->url }}" rel="noopener" target="_blank">Dónde se consigue</a></p>
                        @endif
                    </div>
                </div>

                <div class="fila-acciones">
                    <span @class(['etiqueta', 'etiqueta-alta' => $item->priorityEnum() === \App\Enums\ItemPriority::HIGH])>
                        {{ $item->priorityEnum()->title() }}
                    </span>

                    {{-- El texto y el color los decide el enum; la vista solo
                         decide qué botón acompaña a cada estado. --}}
                    @if ($estado->isOfferable())
                        <form method="POST" action="{{ route('reservations.store', $item) }}">
                            @csrf
                            <button type="submit" class="boton">Lo regalo yo</button>
                        </form>
                    @else
                        <span class="{{ $estado->badge() }}">{{ $estado->title() }}</span>
                    @endif

                    @if ($estado === \App\Enums\GiftState::RESERVED_BY_ME)
                        <form method="POST" action="{{ route('reservations.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="boton-plano">Soltar</button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Las mismas acciones que en la tarjeta, para no obligar a cerrar
                 el detalle y volver a buscar el regalo en la lista. --}}
            <x-producto-modal :id="'regalo-'.$item->id"
                              :producto="$item->product"
                              :titulo="$item->displayName()"
                              :notas="$item->notes"
                              :prioridad="$item->priorityEnum()"
                              :estado="$estado">
                @if ($estado->isOfferable())
                    <form method="POST" action="{{ route('reservations.store', $item) }}">
                        @csrf
                        <button type="submit" class="boton">Lo regalo yo</button>
                    </form>
                @elseif ($estado === \App\Enums\GiftState::RESERVED_BY_ME)
                    <form method="POST" action="{{ route('reservations.destroy', $item) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="boton-plano">Soltar la reserva</button>
                    </form>
                @endif
            </x-producto-modal>
        </article>
    @empty
        <div class="vacio">
            <p>{{ $wishlist->user->publicName() }} todavía no ha puesto nada en esta lista.</p>
        </div>
    @endforelse
@endsection
