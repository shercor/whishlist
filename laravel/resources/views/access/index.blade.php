@extends('layouts.app')

@section('title', 'Solicitudes · whishlist')

@section('contenido')
    <h1>Solicitudes</h1>
    <p class="bajada">
        Todo lo que espera tu respuesta: quién quiere seguirte, quién quiere ver
        tus listas privadas, y qué pasó con lo que pediste tú.
    </p>

    @if ($porResponder->isNotEmpty())
        <h2>Quieren seguirte ({{ $porResponder->count() }})</h2>
        <p class="bajada">
            Aceptarlos les deja ver tus listas públicas, y recién ahí puedes darles una privada.
        </p>
        @include('follows.partials.por-responder')
    @endif

    <h2>Te pidieron una lista</h2>

    @forelse ($recibidas as $solicitud)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$solicitud->user" />
                    <div>
                        <p class="tarjeta-titulo">{{ $solicitud->user->publicName() }} · «{{ $solicitud->wishlist->name }}»</p>
                        <p class="tarjeta-meta">{{ $solicitud->message ?: 'Sin mensaje.' }}</p>
                    </div>
                </div>
                <div class="fila-acciones">
                    @if ($solicitud->statusEnum()->isAwaitingResponse())
                        <form method="POST" action="{{ route('access.update', $solicitud) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ \App\Enums\AccessRequestStatus::APPROVED->label() }}">
                            <button type="submit" class="boton">Aprobar</button>
                        </form>
                        <form method="POST" action="{{ route('access.update', $solicitud) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ \App\Enums\AccessRequestStatus::REJECTED->label() }}">
                            <button type="submit" class="boton-plano boton-peligro">Rechazar</button>
                        </form>
                    @else
                        <span @class([
                            'etiqueta',
                            'etiqueta-ok' => $solicitud->statusEnum()->grantsAccess(),
                        ])>{{ $solicitud->statusEnum()->title() }}</span>

                        @if ($solicitud->statusEnum()->grantsAccess())
                            <form method="POST" action="{{ route('access.update', $solicitud) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Enums\AccessRequestStatus::REVOKED->label() }}">
                                <button type="submit" class="boton-plano boton-peligro">Quitar acceso</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="vacio"><p>Nadie te ha pedido acceso.</p></div>
    @endforelse

    <h2>Pediste tú</h2>

    @forelse ($enviadas as $solicitud)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$solicitud->wishlist->user" />
                    <div>
                    <p class="tarjeta-titulo">Lista de {{ $solicitud->wishlist->user->publicName() }}</p>
                    <p class="tarjeta-meta">
                        @if ($solicitud->responded_at)
                            Respondió el {{ $solicitud->responded_at->translatedFormat('d \d\e F') }}.
                        @else
                            Todavía sin respuesta.
                        @endif
                    </p>
                    </div>
                </div>
                <div class="fila-acciones">
                    <span @class([
                        'etiqueta',
                        'etiqueta-ok' => $solicitud->statusEnum()->grantsAccess(),
                        'etiqueta-espera' => $solicitud->statusEnum()->isAwaitingResponse(),
                    ])>{{ $solicitud->statusEnum()->title() }}</span>

                    @if ($solicitud->statusEnum()->grantsAccess())
                        <a class="boton-plano" href="{{ route('gifts.show', $solicitud->wishlist) }}">Ver la lista</a>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="vacio"><p>No has pedido acceso a ninguna lista.</p></div>
    @endforelse
@endsection
