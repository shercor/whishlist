@extends('errors.layout')

@section('codigo', '503')
@section('titulo', 'Estamos en mantención')
@section('explicacion', 'Volvemos en un rato. Tus listas y tus reservas están intactas.')

@section('salida')
    <a class="boton" href="{{ url("/") }}">Reintentar</a>
@endsection
