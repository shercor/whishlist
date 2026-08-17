@extends('layouts.app')

@section('title', 'Mi perfil · whishlist')

@section('contenido')
    <div class="contenedor angosto" style="padding:0">
        <h1>Mi perfil</h1>
        <p class="bajada">Con qué te encuentran los demás, y cuánto de ti ven.</p>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')

            <div class="campo">
                <label for="username">Tu usuario</label>
                <div class="campo-arroba">
                    <span class="arroba" aria-hidden="true">@</span>
                    <input id="username" type="text" name="username" maxlength="30" required
                           autocapitalize="none" autocorrect="off" spellcheck="false"
                           value="{{ old('username', $usuario->username) }}">
                </div>
                <p class="tarjeta-meta">
                    Es lo único por lo que se te puede buscar. Si lo cambias, el enlace
                    de tu perfil cambia con él y el anterior deja de funcionar.
                </p>
            </div>

            <div class="campo">
                <label for="name">Tu nombre</label>
                <input id="name" type="text" name="name" maxlength="255" required
                       value="{{ old('name', $usuario->name) }}">
            </div>

            <div class="campo">
                <label class="opcion" for="show_name">
                    <input id="show_name" type="checkbox" name="show_name" value="1"
                           @checked(old('show_name', $usuario->show_name))>
                    <span>
                        <strong>Mostrar mi nombre a los demás</strong>
                        <span>
                            Si lo dejas apagado, el resto te ve solo como
                            <strong>{{ $usuario->handle() }}</strong>. Nadie puede buscarte
                            por tu nombre en ningún caso.
                        </span>
                    </span>
                </label>
            </div>

            <div class="acciones">
                <button type="submit" class="boton">Guardar</button>
                <a href="{{ route('users.show', $usuario) }}">Ver mi perfil público</a>
            </div>
        </form>
    </div>
@endsection
