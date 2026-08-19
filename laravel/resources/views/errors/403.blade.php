@extends('errors.layout')

@section('codigo', '403')
@section('titulo', 'Esto no es para ti')

@section('explicacion')
    @php
        /* El mensaje del propio abort() cuando lo hay y dice algo útil —«Esta
           es tu propia lista.»—. Se descarta el de la policy porque es el texto
           de fábrica de Laravel, en inglés, y no explica nada: cuando salta
           por ahí vale más la explicación de abajo. */
        $suyo = trim($exception?->getMessage() ?? '');
        $delFramework = $suyo === '' || $suyo === 'This action is unauthorized.';
    @endphp

    @if ($delFramework)
        No tienes acceso a esto. Si es una lista privada, su dueño decide quién entra;
        puede que nunca te haya invitado, o que te lo haya quitado.
    @else
        {{ $suyo }}
    @endif
@endsection

@section('salida')
    <a class="boton" href="{{ url('/') }}">Ir a mis listas</a>
@endsection
