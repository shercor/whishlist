@extends('layouts.app')

@section('title', 'Agregar regalo · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Agregar un regalo</h1>
            <p class="bajada">A «{{ $wishlist->name }}». Elige algo del catálogo o escríbelo tú.</p>
        </div>
        <a class="boton-plano" href="{{ route('wishlists.show', $wishlist) }}">Volver a la lista</a>
    </div>

    <h2>Buscar en el catálogo</h2>

    <form class="buscador" method="GET" action="{{ route('items.create', $wishlist) }}">
        {{-- autofocus: se llega a esta pantalla para buscar algo, así que el
             cursor ya está donde se va a escribir. Solo cuando no hay búsqueda
             hecha: si la hay, robarle el foco a los resultados hace que un
             Tab desde el campo se salte lo que se acaba de encontrar. --}}
        <input type="search" name="q" value="{{ $termino }}"
               placeholder="peluche, audífonos, libro..." @if ($termino === '') autofocus @endif>
        <button type="submit" class="boton-plano" data-ocupado="Buscando...">Buscar</button>
    </form>

    {{-- Decir cuántos hay y de dónde salen. Sin esto, la lista de doce fichas
         que aparece sin haber buscado nada se lee como si ese fuera el catálogo
         entero, y quien no ve lo que quiere se va pensando que no está. --}}
    @if ($termino === '')
        <p class="tarjeta-meta resumen-busqueda">
            Las {{ $resultados->count() }} fichas más votadas del catálogo. Busca para ver el resto.
        </p>
    @else
        <p class="tarjeta-meta resumen-busqueda">
            {{-- El tope de la consulta son 24: si vienen justo 24, es que
                 seguramente hay más y decir «24 resultados» sería mentir. --}}
            @if ($resultados->count() >= 24)
                Los primeros 24 resultados para «{{ $termino }}». Afina la búsqueda si no ves lo que buscas.
            @else
                {{ $resultados->count() }} {{ Str::plural('resultado', $resultados->count()) }} para «{{ $termino }}».
            @endif
            <a href="{{ route('items.create', $wishlist) }}">Ver las más votadas</a>
        </p>
    @endif

    @forelse ($resultados as $producto)
        @php $leGusta = $producto->isLikedBy(auth()->user()); @endphp

        <article class="tarjeta">
            {{-- El formulario de agregar y el de «me gusta» son hermanos, no
                 anidados: un form dentro de otro no es HTML válido y el
                 navegador se come el de adentro. --}}
            <div class="fila">
                <div class="con-miniatura">
                    @if ($producto->imageSrc())
                        <div class="marco-foto miniatura">
                            <img src="{{ $producto->imageSrc() }}" alt="" loading="lazy">
                        </div>
                    @else
                        <div class="marco-foto miniatura miniatura-vacia" aria-hidden="true">🎁</div>
                    @endif

                    <div>
                        <p class="tarjeta-titulo">
                            <button type="button" class="abre-detalle" data-abre-detalle="producto-{{ $producto->id }}">
                                {{ $producto->name }}
                            </button>
                            @unless ($producto->is_public)
                                <span class="etiqueta">solo tuyo</span>
                            @endunless
                        </p>
                        <p class="tarjeta-meta">
                            {{ $producto->category?->name }}
                            @if ($producto->reference_price)
                                · ${{ number_format($producto->reference_price, 0, ',', '.') }} aprox.
                            @endif
                        </p>

                        @if ($producto->is_public)
                        {{-- El corazón y «Retirar del catálogo» en una fila y no
                             uno sobre otro: son dos formularios hermanos —no se
                             pueden anidar— pero se leen como un mismo pie de la
                             ficha, y apilados hacían de cada resultado una
                             tarjeta el doble de alta. --}}
                        <div class="ficha-pie">
                            <form action="{{ $leGusta ? route('products.unlike', $producto) : route('products.like', $producto) }}"
                                  method="POST">
                                @csrf
                                @if ($leGusta) @method('DELETE') @endif
                                <button type="submit" @class(['megusta', 'marcado' => $leGusta])
                                        title="{{ $leGusta ? 'Quitar tu voto' : 'Esta ficha está bien hecha' }}">
                                    <span class="corazon" aria-hidden="true">{{ $leGusta ? '♥' : '♡' }}</span>
                                    {{ $producto->likes_count }}
                                    <span class="visualmente-oculto">
                                        {{ $leGusta ? 'Quitar tu voto a esta ficha' : 'Votar esta ficha como bien hecha' }}
                                    </span>
                                </button>
                            </form>

                            @can('unpublish', $producto)
                                {{-- Publicar es directo, así que la marcha atrás
                                     tiene que estar a la vista de su autor. Se
                                     pregunta antes: retirarla se lleva por
                                     delante los votos que la ficha juntó, y eso
                                     no se recupera. --}}
                                <form method="POST" action="{{ route('products.unpublish', $producto) }}"
                                      onsubmit="return confirm('¿Retirar «{{ $producto->name }}» del catálogo? Las listas que ya la tienen no cambian, pero se pierden sus votos.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="boton-plano boton-menudo"
                                            title="Dejará de estar en el catálogo de los demás">
                                        Retirar del catálogo
                                    </button>
                                </form>
                            @endcan
                        </div>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('items.store', $wishlist) }}" class="agregar-catalogo">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $producto->id }}">
                    <select name="priority" aria-label="Cuánto quieres «{{ $producto->name }}»">
                        @foreach (\App\Enums\ItemPriority::cases() as $prioridad)
                            <option value="{{ $prioridad->label() }}" @selected($prioridad === \App\Enums\ItemPriority::MEDIUM)>
                                {{ $prioridad->title() }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="boton" data-ocupado="Agregando...">
                        Agregar
                        <span class="visualmente-oculto">{{ $producto->name }} a la lista</span>
                    </button>
                </form>
            </div>

            {{-- Desde el detalle se agrega con prioridad media: quien quiera
                 elegirla tiene el selector en la tarjeta. --}}
            <x-producto-modal :id="'producto-'.$producto->id" :producto="$producto">
                <form method="POST" action="{{ route('items.store', $wishlist) }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $producto->id }}">
                    <input type="hidden" name="priority" value="{{ \App\Enums\ItemPriority::MEDIUM->label() }}">
                    <button type="submit" class="boton">Agregar a mi lista</button>
                </form>
            </x-producto-modal>
        </article>
    @empty
        <div class="vacio">
            <span class="vacio-icono" aria-hidden="true">🔍</span>
            @if ($termino === '')
                <p>El catálogo está vacío.</p>
                <p class="vacio-pista">Escribe el regalo tú mismo aquí abajo.</p>
            @else
                <p>Nada calzó con «{{ $termino }}».</p>
                <p class="vacio-pista">
                    La búsqueda ignora las palabras de menos de tres letras.
                    Si no está en el catálogo, escríbelo tú.
                </p>
            @endif
        </div>
    @endforelse

    {{-- Plegado a propósito.
         Los doce resultados del catálogo y este formulario completo, uno detrás
         del otro, hacían una página de más de tres mil píxeles: quien venía a
         elegir del catálogo tenía que pasar por encima de nueve campos que no
         iba a usar, y quien venía a escribirlo tenía que bajar hasta el final.

         Se abre solo si la validación rebotó —los errores son de este
         formulario, y dejarlo cerrado escondería justo lo que hay que
         arreglar— o si la persona ya había empezado a escribir. --}}
    <details class="desplegable desplegable-suelto" @if ($errors->any() || old('name') !== null) open @endif>
        <summary>O escríbelo tú</summary>

        <div class="desplegable-cuerpo">
        <p class="bajada">
            Queda como producto privado: solo lo ves tú y quien mire esta lista.
            Si quieres, puedes compartir la ficha con el catálogo para que le sirva a más gente.
        </p>

    {{-- enctype: sin esto el navegador manda solo el nombre del archivo y la
         imagen nunca llega. --}}
    <form method="POST" action="{{ route('items.store', $wishlist) }}" enctype="multipart/form-data">
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
            <label for="image">Una foto <span class="pista">(opcional, jpg/png/webp hasta 4 MB)</span></label>

            {{-- La previsualización usa la misma caja cuadrada que la vista
                 final, así lo que se ve acá es exactamente lo que va a quedar
                 y no hay sorpresa al guardar. --}}
            <div class="marco-foto previsualizacion" id="vista-previa" hidden>
                <img src="" alt="Vista previa de la foto que elegiste">
            </div>

            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                   data-previsualiza="#vista-previa">
            <p class="tarjeta-meta">
                No hace falta que sea cuadrada: se muestra entera dentro de un recuadro,
                sin recortarse. Una foto decente es lo que hace que quien te regale sepa que acertó.
            </p>
        </div>

        <div class="campo">
            <label for="reference_price">Precio de referencia <span class="pista">(opcional)</span></label>
            <input id="reference_price" type="number" name="reference_price" min="0" step="1" value="{{ old('reference_price') }}">
            <p class="tarjeta-meta">Solo para orientar a quien vaya a comprarlo; no tiene que ser exacto.</p>
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
                        {{ $prioridad->title() }} — {{ $prioridad->hint() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="campo">
            <label class="opcion" for="share_with_catalog">
                <input id="share_with_catalog" type="checkbox" name="share_with_catalog" value="1"
                       @checked(old('share_with_catalog'))>
                Compartir esta ficha con el catálogo
            </label>
            <p class="tarjeta-meta">
                La ficha —el nombre, la foto y el precio— pasa a estar visible para todos y
                cualquiera puede agregarla a su lista. <strong>Tu lista y tu regalo siguen
                siendo tuyos:</strong> lo que se comparte es la ficha del producto, no que tú lo quieras.
                Puedes retirarla cuando quieras: aparece con un botón para hacerlo en el
                buscador del catálogo de arriba.
            </p>
        </div>

        <div class="acciones">
            <button type="submit" class="boton" data-ocupado="Agregando...">Agregar a la lista</button>
            <a href="{{ route('wishlists.show', $wishlist) }}">Volver a la lista</a>
        </div>
    </form>
        </div>
    </details>
@endsection
