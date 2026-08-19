@extends('errors.layout')

@section('codigo', '500')
@section('titulo', 'Se nos cayó algo')
@section('explicacion', 'El fallo es nuestro, no tuyo. Ya quedó anotado. Si estabas guardando algo, revisa si alcanzó a quedar antes de repetirlo.')

@section('salida')
    <a class="boton" href="{{ url("/") }}">Ir a mis listas</a>
@endsection
