@extends('layouts.app')

@section('title', 'Buscar personas · whishlist')

@section('contenido')
    <h1>Buscar personas</h1>
    <p class="bajada">Se busca por usuario, con o sin el arroba. Los nombres no se buscan.</p>

    {{-- El formulario sigue funcionando solo, sin javascript: envía y la
         pantalla se pinta en el servidor. El menú de sugerencias se monta
         encima y solo adelanta el resultado. --}}
    <form class="buscador" method="GET" action="{{ route('users.search') }}" autocomplete="off">
        <div class="sugerible">
            <input type="search" name="q" id="q" value="{{ $termino }}" placeholder="@ana_perez"
                   autocapitalize="none" autocorrect="off" spellcheck="false" autofocus
                   role="combobox" aria-expanded="false" aria-controls="sugerencias"
                   aria-autocomplete="list" aria-describedby="pista-sugerencias"
                   data-buscador-personas="{{ route('users.suggest') }}">

            <ul class="sugerencias" id="sugerencias" role="listbox" aria-label="Personas encontradas" hidden></ul>
        </div>
        <button type="submit" class="boton-plano">Buscar</button>
    </form>

    <p class="tarjeta-meta" id="pista-sugerencias">
        Desde tres letras te va mostrando coincidencias mientras escribes.
    </p>

    @forelse ($usuarios as $persona)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$persona" />
                    <div>
                        <p class="tarjeta-titulo">
                            <a href="{{ route('users.show', $persona) }}">{{ $persona->handle() }}</a>
                        </p>
                        <p class="tarjeta-meta">
                            @if ($persona->show_name)
                                {{ $persona->name }} ·
                            @endif
                            {{ trans_choice('{0}Sin listas públicas|{1}1 lista pública|[2,*]:count listas públicas', $persona->public_wishlists_count) }}
                        </p>
                    </div>
                </div>
                <div class="fila-acciones">
                    <a class="boton-plano" href="{{ route('users.show', $persona) }}">Ver sus listas</a>
                </div>
            </div>
        </article>
    @empty
        <div class="vacio">
            @if ($termino === '')
                <p>Escribe el usuario de la persona que buscas.</p>
                <p class="tarjeta-meta">
                    A propósito no se muestra a todo el mundo: hay que saber a quién se busca.
                </p>
            @else
                <p>Nadie usa «{{ $termino }}».</p>
                <p class="tarjeta-meta">Revisa cómo se escribe: se busca por el usuario, no por el nombre.</p>
            @endif
        </div>
    @endforelse
@endsection
