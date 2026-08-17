@extends('layouts.app')

@section('title', $wishlist->name.' · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>{{ $wishlist->name }}</h1>
            <p class="bajada">
                {{ $wishlist->description ?: 'Sin descripción.' }}
                @if ($wishlist->event_date)
                    <br>Para el {{ $wishlist->event_date->translatedFormat('d \d\e F \d\e Y') }}.
                @endif
            </p>
        </div>
        <div class="fila-acciones">
            <span class="etiqueta">{{ $wishlist->visibilityEnum()->title() }}</span>
            @unless ($wishlist->visibilityEnum() === \App\Enums\WishlistVisibility::PUBLIC)
                <a class="boton-plano" href="{{ route('access.manage', $wishlist) }}">Quién la ve</a>
            @endunless
            <a class="boton-plano" href="{{ route('wishlists.edit', $wishlist) }}">Editar</a>
            <a class="boton" href="{{ route('items.create', $wishlist) }}">Agregar regalo</a>
        </div>
    </div>

    {{-- Nada en esta pantalla consulta la tabla de reservas: es lo que impide
         que el dueño se entere de quién le va a regalar qué. --}}
    <p class="aviso aviso-ok">
        Acá no verás si alguien reservó tus regalos. Es a propósito: la gracia es la sorpresa.
    </p>

    @if ($wishlist->share_token)
        <div class="campo">
            <label for="enlace">
                Enlace para compartir
                <span class="pista">(quien lo tenga entra aunque no te siga; queda anotado en «Quién la ve»)</span>
            </label>
            <div class="campo-con-boton">
                <input id="enlace" class="enlace-compartir" type="text" readonly
                       onclick="this.select()" value="{{ route('shared.open', $wishlist->share_token) }}">
                <button type="button" class="boton-icono" data-copiar="#enlace"
                        title="Copiar el enlace al portapapeles">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="9" y="9" width="12" height="12" rx="2"/>
                        <path d="M5 15V5a2 2 0 0 1 2-2h10"/>
                    </svg>
                    <span class="visualmente-oculto">Copiar el enlace</span>
                </button>
            </div>
        </div>
    @endif

    @forelse ($items as $item)
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
                            @if ($item->notes) · {{ $item->notes }} @endif
                            @if ($item->isReceived()) · recibido el {{ $item->received_at->translatedFormat('d \d\e F') }} @endif
                        </p>
                    </div>
                </div>
                <div class="fila-acciones">
                    <span @class(['etiqueta', 'etiqueta-alta' => $item->priorityEnum() === \App\Enums\ItemPriority::HIGH])>
                        {{ $item->priorityEnum()->title() }}
                    </span>
                    <form method="POST" action="{{ route('items.received', $item) }}">
                        @csrf
                        <button type="submit" class="boton-plano">
                            {{ $item->isReceived() ? 'No lo he recibido' : 'Ya lo recibí' }}
                        </button>
                    </form>
                </div>
            </div>

            <details>
                <summary class="tarjeta-meta">Editar</summary>
                <form method="POST" action="{{ route('items.update', $item) }}" style="margin-top:1rem">
                    @csrf
                    @method('PATCH')

                    <div class="campo">
                        <label for="alias-{{ $item->id }}">Cómo lo llamas <span class="pista">(en blanco usa «{{ $item->product->name }}»)</span></label>
                        <input id="alias-{{ $item->id }}" type="text" name="alias" maxlength="200" value="{{ $item->alias }}">
                    </div>

                    <div class="campo">
                        <label for="notes-{{ $item->id }}">Notas para quien lo compre</label>
                        <input id="notes-{{ $item->id }}" type="text" name="notes" maxlength="500"
                               value="{{ $item->notes }}" placeholder="Talla, color, dónde se consigue...">
                    </div>

                    <div class="campo">
                        <label for="priority-{{ $item->id }}">Prioridad</label>
                        <select id="priority-{{ $item->id }}" name="priority">
                            @foreach (\App\Enums\ItemPriority::cases() as $prioridad)
                                <option value="{{ $prioridad->label() }}" @selected($item->priority === $prioridad->label())>
                                    {{ $prioridad->title() }} — {{ $prioridad->hint() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="boton">Guardar</button>
                </form>

                {{-- Fuera del formulario de arriba: los form no se anidan. --}}
                <form method="POST" action="{{ route('items.destroy', $item) }}" style="margin-top:0.75rem"
                      onsubmit="return confirm('¿Quitar «{{ $item->displayName() }}» de la lista?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="boton-plano boton-peligro">Quitar de la lista</button>
                </form>
            </details>

            {{-- Sin acciones: es la lista propia, acá no se reserva nada. --}}
            <x-producto-modal :id="'regalo-'.$item->id"
                              :producto="$item->product"
                              :titulo="$item->displayName()"
                              :notas="$item->notes"
                              :prioridad="$item->priorityEnum()" />
        </article>
    @empty
        <div class="vacio">
            <p>Esta lista está vacía.</p>
            <a class="boton" href="{{ route('items.create', $wishlist) }}">Agregar el primer regalo</a>
        </div>
    @endforelse
@endsection
