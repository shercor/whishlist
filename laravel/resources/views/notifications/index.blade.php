@extends('layouts.app')

@section('title', 'Notificaciones · whishlist')

@section('contenido')
    <div class="fila">
        <h1>Notificaciones</h1>
        @if ($notificaciones->whereNull('read_at')->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="boton-plano">Marcar todas como leídas</button>
            </form>
        @endif
    </div>
    <p class="bajada">Lo que pasó mientras no estabas. Solo tú ves esto.</p>

    @forelse ($notificaciones as $notificacion)
        {{-- La tarjeta entera es el enlace: la notificación no es algo que se
             lea y se deje, sino un aviso de que hay algo que atender. --}}
        <article @class(['tarjeta', 'tarjeta-sin-leer' => is_null($notificacion->read_at)])>
            <a class="notificacion" href="{{ route('notifications.open', $notificacion->id) }}">
                <span class="notificacion-icono" aria-hidden="true">{{ $notificacion->data['icono'] ?? '🔔' }}</span>
                <span>
                    <span class="tarjeta-titulo">{{ $notificacion->data['titulo'] }}</span>
                    <span class="tarjeta-meta">{{ $notificacion->data['detalle'] }}</span>
                    <span class="tarjeta-meta">{{ $notificacion->created_at->diffForHumans() }}</span>
                </span>
            </a>
        </article>
    @empty
        <p class="vacio">Nada por ahora. Acá te avisamos cuando alguien pida ver una lista tuya o cuando una reserva tuya esté por vencer.</p>
    @endforelse
@endsection
