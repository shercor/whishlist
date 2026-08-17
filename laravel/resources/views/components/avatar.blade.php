@props([
    'usuario',
    // Tamaños fijos y con nombre, no un número suelto por vista: es lo que
    // mantiene alineadas las listas donde unos tienen foto y otros no.
    // chico = filas densas · normal = tarjetas · grande = cabecera de perfil
    'tamano' => 'normal',
])

{{--
    La cara de una persona.

    Cuando no hay foto no se deja el hueco vacío ni se pone un icono genérico:
    se dibujan sus iniciales sobre un color sacado de su arroba, así que el
    mismo usuario tiene siempre el mismo círculo y se reconoce de una lista a
    otra. Las iniciales salen de publicName(), de modo que quien oculta su
    nombre tampoco filtra sus iniciales.
--}}
@if ($usuario->avatarSrc())
    <img {{ $attributes->class(['avatar', 'avatar-'.$tamano]) }}
         src="{{ $usuario->avatarSrc() }}"
         alt="Foto de {{ $usuario->handle() }}"
         loading="lazy">
@else
    <span {{ $attributes->class(['avatar', 'avatar-'.$tamano, 'avatar-vacio']) }}
          style="--tono: {{ $usuario->avatarHue() }}"
          aria-hidden="true">{{ $usuario->initials() }}</span>
@endif
