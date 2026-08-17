@extends('layouts.app')

@section('title', 'Crear cuenta · whishlist')

@section('contenido')
    <div class="contenedor angosto">
        <h1>Crea tu cuenta</h1>
        <p class="bajada">Es lo único que necesitas para empezar tu primera lista.</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="campo">
                <label for="name">Nombre</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="campo">
                <label for="username">Tu usuario <span class="pista">(así te encuentran los demás)</span></label>
                <div class="campo-arroba">
                    <span class="arroba" aria-hidden="true">@</span>
                    <input id="username" type="text" name="username" value="{{ old('username') }}"
                           maxlength="30" required autocapitalize="none" autocorrect="off" spellcheck="false"
                           placeholder="ana_perez">
                </div>
                <p class="tarjeta-meta">Letras, números y guion bajo. Es lo único por lo que se te puede buscar: tu nombre queda privado salvo que tú lo cambies.</p>
            </div>

            <div class="campo">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" required>
            </div>

            <div class="campo">
                <label for="password_confirmation">Repite la contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>

            <div class="acciones">
                <button type="submit" class="boton">Crear cuenta</button>
                <a href="{{ route('login') }}">Ya tengo cuenta</a>
            </div>
        </form>
    </div>
@endsection
