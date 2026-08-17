@extends('layouts.app')

@section('title', $usuario->handle().' · whishlist')

@section('contenido')
    <div class="encabezado">
        <div class="persona">
            <x-avatar :usuario="$usuario" tamano="grande" />
            <div>
            <h1>{{ $usuario->handle() }}</h1>
            <p class="bajada">
                @if ($usuario->show_name)
                    {{ $usuario->name }}.
                @endif
                @if ($esMiPerfil)
                    Así te ven los demás.
                @elseif ($usuario->is_private)
                    Perfil privado.
                @else
                    Perfil público.
                @endif
            </p>
            </div>
        </div>

        <div class="fila-acciones">
            @if ($esMiPerfil)
                <a class="boton-plano" href="{{ route('profile.edit') }}">Editar mi perfil</a>
            @elseif (! $seguimiento)
                <form method="POST" action="{{ route('follows.store', $usuario) }}">
                    @csrf
                    <button type="submit" class="boton">
                        {{ $usuario->is_private ? 'Pedir seguirlo' : 'Seguir' }}
                    </button>
                </form>
            @elseif (! $seguimiento->isAccepted())
                <span class="etiqueta etiqueta-espera">Solicitud pendiente</span>
                <form method="POST" action="{{ route('follows.destroy', $usuario) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="boton-plano">Cancelar</button>
                </form>
            @else
                <span class="etiqueta etiqueta-ok">Lo sigues</span>
                {{-- Dejar de seguir cierra en el acto las listas privadas que
                     esta persona te haya dado. --}}
                <form method="POST" action="{{ route('follows.destroy', $usuario) }}"
                      onsubmit="return confirm('¿Dejar de seguir a {{ $usuario->handle() }}? Perderás el acceso a sus listas privadas.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="boton-plano boton-peligro">Dejar de seguir</button>
                </form>
            @endif
        </div>
    </div>

    @unless ($puedeVer)
        <div class="vacio">
            <p>Este perfil es privado.</p>
            <p class="tarjeta-meta">
                {{ $seguimiento ? 'Cuando acepte tu solicitud verás sus listas.' : 'Pídele seguirlo para ver sus listas.' }}
            </p>
        </div>
    @else
        @forelse ($wishlists as $wishlist)
            <article class="tarjeta">
                <div class="fila">
                    <div>
                        <p class="tarjeta-titulo">
                            <a href="{{ $esMiPerfil ? route('wishlists.show', $wishlist) : route('gifts.show', $wishlist) }}">
                                {{ $wishlist->name }}
                            </a>
                        </p>
                        <p class="tarjeta-meta">
                            {{ trans_choice('{0}Sin regalos|{1}1 regalo|[2,*]:count regalos', $wishlist->items_count) }}
                            @if ($wishlist->event_date)
                                · para el {{ $wishlist->event_date->translatedFormat('d \d\e F') }}
                            @endif
                        </p>
                    </div>
                    <div class="fila-acciones">
                        <a class="boton" href="{{ $esMiPerfil ? route('wishlists.show', $wishlist) : route('gifts.show', $wishlist) }}">
                            {{ $esMiPerfil ? 'Ver' : 'Regalarle algo' }}
                        </a>
                    </div>
                </div>
            </article>
        @empty
            <div class="vacio">
                <p>
                    @if ($esMiPerfil)
                        No tienes listas públicas. Las privadas y las de enlace no salen acá.
                    @else
                        {{ $usuario->handle() }} no tiene listas públicas.
                    @endif
                </p>
                @unless ($esMiPerfil)
                    {{-- Si tiene listas privadas, no se dice: que existan es cosa suya. --}}
                    <p class="tarjeta-meta">Si te compartió el enlace de una lista, ábrelo directamente.</p>
                @endunless
            </div>
        @endforelse
    @endunless
@endsection
