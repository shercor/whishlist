{{-- Interruptor de tema claro/oscuro.

     Los dos iconos se pintan siempre y el CSS esconde el que sobra según el
     tema activo. Así el botón ya sale correcto en el primer pintado, incluso
     antes de que corra el javascript. --}}
<button type="button"
        @class(['tema', 'tema-suelto' => $suelto ?? false])
        data-cambia-tema
        title="Cambiar entre modo claro y oscuro">
    <span class="luna" aria-hidden="true">☾</span>
    <span class="sol" aria-hidden="true">☀</span>
    <span class="visualmente-oculto">Cambiar entre modo claro y oscuro</span>
</button>
