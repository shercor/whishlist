@extends('layouts.app')

@section('title', 'Quién ve «'.$wishlist->name.'» · whishlist')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Quién ve «{{ $wishlist->name }}»</h1>
            <p class="bajada">
                Los que entraron, por dónde entraron, y a quién más puedes dársela.
            </p>
        </div>
        <div class="fila-acciones">
            <a class="boton-plano" href="{{ route('wishlists.show', $wishlist) }}">Volver a la lista</a>
        </div>
    </div>

    <h2>Con acceso ({{ $accesos->count() }})</h2>

    @forelse ($accesos as $acceso)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$acceso->user" />
                    <div>
                        <p class="tarjeta-titulo">
                            <a href="{{ route('users.show', $acceso->user) }}">{{ $acceso->user->publicName() }}</a>
                        </p>
                        <p class="tarjeta-meta">
                            {{ $acceso->sourceEnum()->title() }}
                            @if ($acceso->message) · «{{ $acceso->message }}» @endif
                        </p>
                    </div>
                </div>
                <div class="fila-acciones">
                    <span @class([
                        'etiqueta',
                        'etiqueta-ok' => $acceso->statusEnum()->grantsAccess(),
                        'etiqueta-espera' => $acceso->statusEnum()->isAwaitingResponse(),
                    ])>{{ $acceso->statusEnum()->title() }}</span>

                    @if ($acceso->statusEnum()->isAwaitingResponse())
                        <form method="POST" action="{{ route('access.update', $acceso) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ \App\Enums\AccessRequestStatus::APPROVED->label() }}">
                            <button type="submit" class="boton">Darle acceso</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('access.revoke', [$wishlist, $acceso]) }}"
                          onsubmit="return confirm('¿Quitarle el acceso a {{ $acceso->user->handle() }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="boton-plano boton-peligro">Quitar</button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>Nadie ha entrado a esta lista todavía.</p>
        </div>
    @endforelse

    <h2>Dársela a alguien</h2>
    <p class="bajada">
        Solo aparecen tus seguidores: una lista privada no se reparte entre desconocidos.
    </p>

    @forelse ($invitables as $seguidor)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$seguidor" />
                    <div>
                        <p class="tarjeta-titulo">{{ $seguidor->publicName() }}</p>
                        <p class="tarjeta-meta">{{ $seguidor->handle() }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('access.invite', $wishlist) }}" class="fila-acciones">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $seguidor->id }}">
                    <button type="submit" class="boton">Darle la lista</button>
                </form>
            </div>
        </article>
    @empty
        <div class="vacio">
            <p>No te queda ningún seguidor a quien dársela.</p>
            <a class="boton-plano" href="{{ route('follows.index') }}">Ver mi gente</a>
        </div>
    @endforelse
@endsection
