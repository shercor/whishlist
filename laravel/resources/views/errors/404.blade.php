@extends('errors.layout')

@section('codigo', '404')
@section('titulo', 'Esto no está acá')
@section('explicacion', 'La dirección no existe, o lo que había se borró. Si llegaste por un enlace que te pasaron, pide que te lo manden otra vez.')

@section('salida')
    <a class="boton" href="{{ url("/") }}">Ir a mis listas</a>
@endsection
