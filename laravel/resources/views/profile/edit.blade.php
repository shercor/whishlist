@extends('layouts.app')

@section('title', 'Mi perfil · whishlist')

@section('contenido')
    <div class="contenedor angosto" style="padding:0">
        <h1>Mi perfil</h1>
        <p class="bajada">Con qué te encuentran los demás, y cuánto de ti ven.</p>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="campo">
                <label for="avatar">Foto de perfil</label>
                <div class="avatar-elegir">
                    {{-- La actual, o el placeholder que se está usando hoy. --}}
                    <x-avatar :usuario="$usuario" tamano="grande" id="avatar-actual" />
                    <img class="avatar avatar-grande" id="vista-avatar" src="" alt="" hidden>

                    <div>
                        <input id="avatar" type="file" name="avatar"
                               accept="image/jpeg,image/png,image/webp"
                               data-previsualiza-avatar="#vista-avatar" data-oculta="#avatar-actual">

                        @if ($usuario->avatarSrc())
                            <label class="tarjeta-meta" for="quitar_avatar" style="display:block;margin-top:0.5rem">
                                <input id="quitar_avatar" type="checkbox" name="quitar_avatar" value="1">
                                Quitar mi foto y volver a las iniciales
                            </label>
                        @endif
                    </div>
                </div>
            </div>

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
                <label class="opcion" for="perfil_publico">
                    <input id="perfil_publico" type="checkbox" name="perfil_publico" value="1"
                           @checked(old('perfil_publico', ! $usuario->is_private))>
                    <span>
                        <strong>Perfil público</strong>
                        <span>
                            Con el perfil público, cualquiera puede ver tus listas públicas
                            y seguirte sin pedirte permiso. Con el perfil privado —lo normal—
                            no se ve <em>ninguna</em> lista tuya hasta que aceptes a esa persona
                            como seguidora, y tus listas dejan de aparecer en «Descubrir».
                        </span>
                    </span>
                </label>
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
