@extends('layouts.app')

@section('title', $usuario->handle().' · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>{{ $usuario->handle() }}</h1>
            <p class="bajada">
                @if ($usuario->show_name)
                    {{ $usuario->name }}.
                @endif
                @if ($esMiPerfil)
                    Así te ven los demás.
                @else
                    Sus listas públicas.
                @endif
            </p>
        </div>
        @if ($esMiPerfil)
            <div class="fila-acciones">
                <a class="boton-plano" href="{{ route('profile.edit') }}">Editar mi perfil</a>
            </div>
        @endif
    </div>

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
@endsection
