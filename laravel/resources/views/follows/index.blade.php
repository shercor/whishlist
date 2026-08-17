@extends('layouts.app')

@section('title', 'Mi gente · whishlist')

@section('contenido')
    <h1>Mi gente</h1>
    <p class="bajada">
        Quién te sigue y a quién sigues. Solo tus seguidores pueden recibir una lista privada tuya.
    </p>

    @if ($porResponder->isNotEmpty())
        <h2>Te quieren seguir</h2>
        @include('follows.partials.por-responder')
    @endif

    <h2>Te siguen ({{ $seguidores->count() }})</h2>
    @forelse ($seguidores as $seguimiento)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$seguimiento->follower" />
                    <div>
                        <p class="tarjeta-titulo">
                            <a href="{{ route('users.show', $seguimiento->follower) }}">
                                {{ $seguimiento->follower->publicName() }}
                            </a>
                        </p>
                        <p class="tarjeta-meta">Puede recibir tus listas privadas.</p>
                    </div>
                </div>
                <div class="fila-acciones">
                    {{-- Quitarlo le cierra en el acto las listas privadas que
                         le hubieras dado. --}}
                    <form method="POST" action="{{ route('follows.remove', $seguimiento->follower) }}"
                          onsubmit="return confirm('¿Quitar a {{ $seguimiento->follower->handle() }}? Perderá el acceso a tus listas privadas.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="boton-plano boton-peligro">Quitar</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>Todavía no te sigue nadie.</p>
            <p class="tarjeta-meta">Pásale tu {{ auth()->user()->handle() }} a quien quieras que vea tus listas.</p>
        </div>
    @endforelse

    <h2>Sigues a ({{ $siguiendo->count() }})</h2>
    @forelse ($siguiendo as $seguimiento)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$seguimiento->followed" />
                    <div>
                        <p class="tarjeta-titulo">
                            <a href="{{ route('users.show', $seguimiento->followed) }}">
                                {{ $seguimiento->followed->publicName() }}
                            </a>
                        </p>
                    </div>
                </div>
                <div class="fila-acciones">
                    <a class="boton-plano" href="{{ route('users.show', $seguimiento->followed) }}">Ver sus listas</a>
                </div>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>No sigues a nadie.</p>
            <a class="boton" href="{{ route('users.search') }}">Buscar personas</a>
        </div>
    @endforelse

    @if ($misPendientes->isNotEmpty())
        <h2>Esperando respuesta</h2>
        @foreach ($misPendientes as $seguimiento)
            <article class="tarjeta">
                <div class="fila">
                    <div class="persona">
                        <x-avatar :usuario="$seguimiento->followed" />
                        <div>
                            <p class="tarjeta-titulo">{{ $seguimiento->followed->handle() }}</p>
                            <p class="tarjeta-meta">Todavía no acepta que lo sigas.</p>
                        </div>
                    </div>
                    <div class="fila-acciones">
                        <form method="POST" action="{{ route('follows.destroy', $seguimiento->followed) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="boton-plano">Cancelar</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    @endif
@endsection
