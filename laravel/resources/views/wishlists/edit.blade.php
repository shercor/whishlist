@extends('layouts.app')

@section('title', 'Editar lista · whishlist')

@section('contenido')
    <h1>Editar «{{ $wishlist->name }}»</h1>
    <p class="bajada">Si dejas de compartirla por enlace, el enlace anterior deja de servir.</p>

    <form method="POST" action="{{ route('wishlists.update', $wishlist) }}">
        @csrf
        @method('PUT')
        @include('wishlists.partials.form', ['wishlist' => $wishlist])

        <div class="acciones">
            <button type="submit" class="boton">Guardar</button>
            <a href="{{ route('wishlists.show', $wishlist) }}">Cancelar</a>
        </div>
    </form>

    @if ($pendientes->isNotEmpty())
        <h2>Te pidieron acceso</h2>
        @foreach ($pendientes as $solicitud)
            <article class="tarjeta">
                <div class="fila">
                    <div>
                        <p class="tarjeta-titulo">{{ $solicitud->user->name }}</p>
                        <p class="tarjeta-meta">{{ $solicitud->message ?: 'Sin mensaje.' }}</p>
                    </div>
                    <div class="fila-acciones">
                        <form method="POST" action="{{ route('access.update', $solicitud) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ \App\Enums\AccessRequestStatus::APPROVED->label() }}">
                            <button type="submit" class="boton-plano">Aprobar</button>
                        </form>
                        <form method="POST" action="{{ route('access.update', $solicitud) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ \App\Enums\AccessRequestStatus::REJECTED->label() }}">
                            <button type="submit" class="boton-plano boton-peligro">Rechazar</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    @endif

    <h2>Eliminar</h2>
    <article class="tarjeta">
        <div class="fila">
            <p class="tarjeta-meta">Se va la lista con todos sus regalos. No se puede deshacer.</p>
            <form method="POST" action="{{ route('wishlists.destroy', $wishlist) }}"
                  onsubmit="return confirm('¿Eliminar «{{ $wishlist->name }}» y todos sus regalos?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="boton-plano boton-peligro">Eliminar lista</button>
            </form>
        </div>
    </article>
@endsection
