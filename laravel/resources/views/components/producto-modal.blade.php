@props([
    // Id del <dialog>, para que el botón que lo abre lo encuentre.
    'id',
    'producto',
    // Cómo se llama en la lista: puede diferir del nombre del catálogo.
    'titulo' => null,
    'notas' => null,
    'prioridad' => null,
    // Estado del regalo para quien mira. Solo llega desde la lista de otro.
    'estado' => null,
])

{{--
    El detalle de un regalo, en grande.

    Se usa <dialog> nativo y no un div con clases: trae gratis el foco atrapado
    dentro, cerrar con Escape, el fondo inerte y el backdrop. Reimplementar eso
    a mano es de donde salen los modales que no se pueden cerrar con teclado.

    Las acciones —reservar, soltar, agregar— llegan por el slot, porque cada
    pantalla ofrece cosas distintas sobre el mismo producto.
--}}
<dialog id="{{ $id }}" class="modal" aria-labelledby="{{ $id }}-titulo">
    <div class="modal-cuerpo">
        <div class="modal-encabezado">
            <h2 id="{{ $id }}-titulo">{{ $titulo ?? $producto->name }}</h2>
            <button type="button" class="boton-icono" data-cierra-modal title="Cerrar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
                <span class="visualmente-oculto">Cerrar</span>
            </button>
        </div>

        @if ($producto->imageSrc())
            <div class="marco-foto grande">
                {{-- loading="lazy" no sirve acá: el modal está oculto y la
                     imagen no se pediría hasta abrirlo, que es justo cuando ya
                     se necesita. --}}
                <img src="{{ $producto->imageSrc() }}" alt="Foto de {{ $producto->name }}">
            </div>
        @endif

        <ul class="modal-datos">
            @if ($titulo && $titulo !== $producto->name)
                <li><span class="clave">En el catálogo</span> <span>{{ $producto->name }}</span></li>
            @endif

            @if ($producto->category)
                <li><span class="clave">Categoría</span> <span>{{ $producto->category->name }}</span></li>
            @endif

            @if ($producto->reference_price)
                <li>
                    <span class="clave">Precio de referencia</span>
                    <span>${{ number_format($producto->reference_price, 0, ',', '.') }} aprox.</span>
                </li>
            @endif

            @if ($prioridad)
                <li>
                    <span class="clave">Cuánto lo quiere</span>
                    <span>{{ $prioridad->title() }} — {{ $prioridad->hint() }}</span>
                </li>
            @endif

            @if ($estado)
                <li><span class="clave">Estado</span> <span>{{ $estado->title() }}</span></li>
            @endif

            @if ($notas)
                <li><span class="clave">Notas</span> <span>{{ $notas }}</span></li>
            @endif

            @if ($producto->description)
                <li><span class="clave">Descripción</span> <span>{{ $producto->description }}</span></li>
            @endif

            @if ($producto->url)
                <li>
                    <span class="clave">Dónde se consigue</span>
                    <span><a href="{{ $producto->url }}" rel="noopener" target="_blank">Ver en la tienda</a></span>
                </li>
            @endif
        </ul>

        <div class="modal-acciones">
            {{ $slot }}
            <button type="button" class="boton-plano" data-cierra-modal>Cerrar</button>
        </div>
    </div>
</dialog>
