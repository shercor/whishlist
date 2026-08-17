@extends('layouts.app')

@section('title', 'Nueva lista · whishlist')

@section('contenido')
    <h1>Nueva lista</h1>
    <p class="bajada">Puedes cambiar todo esto después.</p>

    <form method="POST" action="{{ route('wishlists.store') }}">
        @csrf
        @include('wishlists.partials.form', ['wishlist' => null])

        <div class="acciones">
            <button type="submit" class="boton">Crear lista</button>
            <a href="{{ route('wishlists.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
