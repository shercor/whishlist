@extends('layouts.app')

@section('title', 'Agregar regalo · whishlist')

@section('contenido')
    <h1>Agregar un regalo</h1>
    <p class="bajada">A «{{ $wishlist->name }}». Elige algo del catálogo o escríbelo tú.</p>

    <h2>Buscar en el catálogo</h2>

    <form class="buscador" method="GET" action="{{ route('items.create', $wishlist) }}">
        <input type="search" name="q" value="{{ $termino }}" placeholder="peluche, audífonos, libro...">
        <button type="submit" class="boton-plano">Buscar</button>
    </form>

    @forelse ($resultados as $producto)
        <article class="tarjeta">
            <form method="POST" action="{{ route('items.store', $wishlist) }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $producto->id }}">

                <div class="fila">
                    <div>
                        <p class="tarjeta-titulo">
                            {{ $producto->name }}
                            @unless ($producto->is_public)
                                <span class="etiqueta">solo tuyo</span>
                            @endunless
                        </p>
                        <p class="tarjeta-meta">
                            {{ $producto->category?->name }}
                            @if ($producto->reference_price)
                                · ${{ number_format($producto->reference_price, 0, ',', '.') }}
                            @endif
                        </p>
                    </div>
                    <div class="fila-acciones">
                        <select name="priority" aria-label="Prioridad">
                            @foreach (\App\Enums\ItemPriority::cases() as $prioridad)
                                <option value="{{ $prioridad->label() }}" @selected($prioridad === \App\Enums\ItemPriority::MEDIUM)>
                                    {{ ucfirst($prioridad->label()) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="boton">Agregar</button>
                    </div>
                </div>
            </form>
        </article>
    @empty
        <div class="vacio">
            @if ($termino === '')
                <p>El catálogo está vacío.</p>
            @else
                <p>Nada calzó con «{{ $termino }}». Escríbelo tú aquí abajo.</p>
                <p class="tarjeta-meta">La búsqueda ignora las palabras de menos de tres letras.</p>
            @endif
        </div>
    @endforelse

    <h2>O escríbelo tú</h2>
    <p class="bajada">Queda como producto privado: solo lo ves tú y quien mire esta lista.</p>

    <form method="POST" action="{{ route('items.store', $wishlist) }}">
        @csrf

        <div class="campo">
            <label for="name">Qué es</label>
            <input id="name" type="text" name="name" maxlength="200" value="{{ old('name') }}"
                   placeholder="Taza de greca de la feria de Valparaíso">
        </div>

        <div class="campo">
            <label for="category_id">Categoría</label>
            <select id="category_id" name="category_id">
                @foreach ($categories as $categoria)
                    <option value="{{ $categoria->id }}" @selected(old('category_id') == $categoria->id)>
                        {{ $categoria->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="campo">
            <label for="description">Cómo es <span class="pista">(opcional)</span></label>
            <textarea id="description" name="description" maxlength="1000">{{ old('description') }}</textarea>
        </div>

        <div class="campo">
            <label for="url">Dónde se consigue <span class="pista">(opcional)</span></label>
            <input id="url" type="url" name="url" maxlength="2048" value="{{ old('url') }}" placeholder="https://...">
        </div>

        <div class="campo">
            <label for="reference_price">Precio de referencia <span class="pista">(opcional)</span></label>
            <input id="reference_price" type="number" name="reference_price" min="0" step="1" value="{{ old('reference_price') }}">
        </div>

        <div class="campo">
            <label for="notes">Notas para quien lo compre <span class="pista">(opcional)</span></label>
            <input id="notes" type="text" name="notes" maxlength="500" value="{{ old('notes') }}"
                   placeholder="Talla M, ojalá azul">
        </div>

        <div class="campo">
            <label for="priority">Cuánto lo quieres</label>
            <select id="priority" name="priority">
                @foreach (\App\Enums\ItemPriority::cases() as $prioridad)
                    <option value="{{ $prioridad->label() }}" @selected(old('priority', \App\Enums\ItemPriority::MEDIUM->label()) === $prioridad->label())>
                        {{ ucfirst($prioridad->label()) }} — {{ $prioridad->hint() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="acciones">
            <button type="submit" class="boton">Agregar a la lista</button>
            <a href="{{ route('wishlists.show', $wishlist) }}">Volver a la lista</a>
        </div>
    </form>
@endsection
