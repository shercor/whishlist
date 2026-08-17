@extends('layouts.app')

@section('title', 'Lista privada · whishlist')

@section('contenido')
    {{-- A propósito no se nombra la lista: si no, probando ids cualquiera
         averiguaría qué listas privadas tiene una persona. --}}
    <h1>Esta lista es privada</h1>
    <p class="bajada">{{ $wishlist->user->name }} decide quién la ve.</p>

    @if ($yaPidio)
        <div class="vacio">
            <p>Ya le pediste acceso. Cuando responda lo verás en <a href="{{ route('access.index') }}">Solicitudes</a>.</p>
        </div>
    @elseif (! $puedePedir)
        <div class="vacio">
            <p>Esta lista se comparte solo por enlace. Pídeselo a {{ $wishlist->user->name }}.</p>
            <a class="boton-plano" href="{{ route('discover') }}">Volver</a>
        </div>
    @else
        <form method="POST" action="{{ route('access.store', $wishlist) }}">
            @csrf

            <div class="campo">
                <label for="message">Cuéntale quién eres <span class="pista">(opcional)</span></label>
                <input id="message" type="text" name="message" maxlength="300"
                       placeholder="Soy Bruno, tu primo.">
            </div>

            <div class="acciones">
                <button type="submit" class="boton">Pedir acceso</button>
                <a href="{{ route('discover') }}">Volver</a>
            </div>
        </form>
    @endif
@endsection
