@extends('errors.layout')

@section('codigo', '429')
@section('titulo', 'Vas muy rápido')
@section('explicacion', 'Hiciste demasiadas peticiones seguidas. Espera un momento y vuelve a intentarlo.')

@section('salida')
    <a class="boton" href="{{ url("/") }}">Ir a mis listas</a>
@endsection
