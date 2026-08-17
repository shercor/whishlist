@extends('layouts.app')

@section('title', 'Entrar · whishlist')

@section('contenido')
    <div class="contenedor angosto">
        <h1>Entra a whishlist</h1>
        <p class="bajada">Arma tus listas de deseos y reserva regalos sin arruinar la sorpresa.</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="campo">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required>
            </div>

            <label class="opcion">
                <input type="checkbox" name="remember" value="1">
                <span>No cerrar sesión</span>
            </label>

            <div class="acciones">
                <button type="submit" class="boton">Entrar</button>
                <a href="{{ route('register') }}">Crear una cuenta</a>
            </div>
        </form>
    </div>
@endsection
