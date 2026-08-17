{{-- Las solicitudes de seguimiento que esperan mi respuesta.

     Vive en un parcial porque se muestra en dos pantallas: en «Solicitudes»,
     que es la bandeja de todo lo que espera respuesta, y en «Mi gente», que es
     donde uno administra a las personas. Tenerla en un solo lugar obligaba a
     adivinar cuál de los dos era, y ya pasó: se buscó en Solicitudes y no
     estaba. --}}
@if ($porResponder->isNotEmpty())
    @foreach ($porResponder as $solicitud)
        <article class="tarjeta">
            <div class="fila">
                <div class="persona">
                    <x-avatar :usuario="$solicitud->follower" />
                    <div>
                        <p class="tarjeta-titulo">
                            <a href="{{ route('users.show', $solicitud->follower) }}">
                                {{ $solicitud->follower->publicName() }}
                            </a>
                        </p>
                        <p class="tarjeta-meta">
                            Quiere seguirte · pidió el
                            {{ $solicitud->created_at->translatedFormat('d \d\e F') }}
                        </p>
                    </div>
                </div>
                <div class="fila-acciones">
                    <form method="POST" action="{{ route('follows.update', $solicitud) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="aceptar">
                        <button type="submit" class="boton">Aceptar</button>
                    </form>
                    <form method="POST" action="{{ route('follows.update', $solicitud) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="rechazar">
                        <button type="submit" class="boton-plano boton-peligro">Rechazar</button>
                    </form>
                </div>
            </div>
        </article>
    @endforeach
@endif
