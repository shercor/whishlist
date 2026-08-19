@extends('errors.layout')

@section('codigo', '419')
@section('titulo', 'Se te venció la sesión')
@section('explicacion', 'Pasó demasiado rato con la página abierta y el formulario caducó por seguridad. No se guardó nada: vuelve a entrar y repítelo.')

@section('salida')
    <a class="boton" href="{{ route("login") }}">Volver a entrar</a>
@endsection
