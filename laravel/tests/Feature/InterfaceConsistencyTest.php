<?php

namespace Tests\Feature;

use App\Enums\WishlistVisibility;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que la interfaz promete en todas las pantallas.
 *
 * Nada de esto es una regla de negocio: son las cosas del rework de apariencia
 * que se rompen en silencio. Una pantalla nueva que se olvide del ancla del
 * contenido, o un aviso permanente pintado del verde de «salió bien», no hacen
 * fallar nada —solo dejan la aplicación un poco peor cada vez—.
 */
class InterfaceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function pantallas(User $duenio, Wishlist $lista): array
    {
        return [
            'mis listas' => route('wishlists.index'),
            'crear lista' => route('wishlists.create'),
            'ver lista' => route('wishlists.show', $lista),
            'editar lista' => route('wishlists.edit', $lista),
            'agregar regalo' => route('items.create', $lista),
            'descubrir' => route('discover'),
            'buscar personas' => route('users.search'),
            'mi perfil' => route('profile.edit'),
            'mi gente' => route('follows.index'),
            'solicitudes' => route('access.index'),
            'notificaciones' => route('notifications.index'),
            'voy a regalar' => route('reservations.index'),
        ];
    }

    private function escena(): array
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $duenio->id]);

        WishlistItem::factory()->for($lista)->create();
        Product::factory()->create();

        return [$duenio, $lista];
    }

    /**
     * El enlace de «saltar al contenido» y su destino, en todas las pantallas.
     *
     * Van juntos porque solos no sirven: el enlace sin el ancla no lleva a
     * ningún lado, y el ancla sin enlace no la alcanza nadie. Una pantalla
     * nueva que se cuelgue de otro layout se quedaría sin los dos y no habría
     * forma de notarlo mirando.
     */
    public function test_every_screen_can_be_skipped_to_its_content(): void
    {
        [$duenio, $lista] = $this->escena();

        foreach ($this->pantallas($duenio, $lista) as $nombre => $url) {
            $html = $this->actingAs($duenio)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('href="#contenido"', $html, "«{$nombre}» no tiene el enlace de saltar al contenido");
            $this->assertStringContainsString('id="contenido"', $html, "«{$nombre}» no tiene a dónde saltar");
        }
    }

    /**
     * El foco del teclado tiene que poder aterrizar en el contenido.
     *
     * Sin `tabindex="-1"` el navegador mueve el scroll pero deja el foco donde
     * estaba, así que el Tab siguiente vuelve al principio de la barra: el
     * enlace parece funcionar y en realidad no sirve de nada.
     */
    public function test_the_content_can_receive_the_focus(): void
    {
        [$duenio] = $this->escena();

        $this->actingAs($duenio)
            ->get(route('wishlists.index'))
            ->assertSee('id="contenido" tabindex="-1"', false);
    }

    /**
     * Los dos avisos permanentes explican una regla; no son el resultado de
     * nada que la persona acabe de hacer.
     *
     * Pintados con `aviso-ok` competían con los avisos de «guardado» y le
     * quitaban el significado al verde: si el mismo color sale cuando guardas
     * algo y cuando la página te explica cómo funciona, deja de decir nada.
     */
    public function test_the_permanent_notices_are_not_dressed_as_success(): void
    {
        [$duenio, $lista] = $this->escena();

        $propia = $this->actingAs($duenio)->get(route('wishlists.show', $lista))->getContent();

        $this->assertStringContainsString('aviso aviso-info', $propia);
        $this->assertStringNotContainsString('aviso aviso-ok', $propia);

        $ajena = $this->actingAs(User::factory()->create())
            ->get(route('gifts.show', $lista))->getContent();

        $this->assertStringContainsString('aviso aviso-info', $ajena);
        $this->assertStringNotContainsString('aviso aviso-ok', $ajena);
    }

    /**
     * El formulario de escribir un regalo a mano viene plegado —si no, la
     * pantalla son tres mil píxeles de los que dos tercios no se usan— pero
     * **se abre solo cuando la validación rebota**.
     *
     * Cerrado, los errores quedan escondidos justo debajo del resumen que los
     * anuncia: la persona lee «el nombre es obligatorio» y no ve ningún campo.
     */
    public function test_the_manual_gift_form_opens_itself_when_the_form_was_rejected(): void
    {
        [$duenio, $lista] = $this->escena();

        // Por expresión regular y no por texto exacto: Blade deja el atributo
        // separado por los espacios de su propio `@if`, y una cadena literal se
        // rompería con solo reordenar la línea.
        $abre = '/<details[^>]*desplegable-suelto[^>]*\sopen[\s>]/';

        $plegado = $this->actingAs($duenio)->get(route('items.create', $lista))->getContent();
        $this->assertDoesNotMatchRegularExpression($abre, $plegado,
            'el formulario viene desplegado sin motivo');

        // `from(...)`: la validación rebota con `back()`, así que sin decir de
        // dónde viene la petición el redirect se va a la raíz y esto no probaría
        // nada de esta pantalla.
        $abierto = $this->actingAs($duenio)
            ->from(route('items.create', $lista))
            ->followingRedirects()
            ->post(route('items.store', $lista), ['name' => ''])
            ->getContent();

        $this->assertMatchesRegularExpression($abre, $abierto,
            'el formulario quedó plegado con sus propios errores dentro');
    }

    /**
     * Las páginas de error son de la aplicación: en español, con el tema
     * puesto y con una salida.
     *
     * Antes eran las de fábrica de Laravel: «404 | Not Found» sobre fondo
     * blanco pasara lo que pasara con el tema, y sin un solo enlace de vuelta.
     * Un 403 al abrir la lista de alguien es una situación **normal** acá, no
     * un fallo del que haya que salir recargando.
     */
    public function test_the_error_pages_are_ours(): void
    {
        $html = $this->get('/una-ruta-que-no-existe')->assertNotFound()->getContent();

        $this->assertStringContainsString('Esto no está acá', $html);
        $this->assertStringNotContainsString('Not Found', $html);
        // La salida: sin esto la única forma de volver es la flecha del navegador.
        $this->assertStringContainsString('Ir a mis listas', $html);
        // El tema, o se aterriza en blanco viniendo de una aplicación oscura.
        $this->assertStringContainsString("localStorage.getItem('tema')", $html);
    }

    /**
     * La página de error **no** pinta la barra de navegación, y es a propósito.
     *
     * La barra le pregunta a la base cuántas solicitudes y notificaciones hay
     * sin ver. En un 500 provocado por la base, pintarla volvería a reventar
     * dentro del propio manejador de errores y lo que se ve es una pantalla en
     * blanco. Por eso se comprueba que la página de error se renderiza **sin
     * sesión iniciada** y sin rastro de la barra.
     */
    public function test_an_error_page_does_not_depend_on_the_navigation_bar(): void
    {
        $html = $this->get('/una-ruta-que-no-existe')->getContent();

        $this->assertStringNotContainsString('class="barra"', $html);
        $this->assertStringNotContainsString('Voy a regalar', $html);
    }

    /**
     * Cuando el 403 trae una explicación propia, se muestra esa.
     *
     * El caso real es el dueño abriendo la pantalla de regalar de su propia
     * lista: `abort(403, 'Esta es tu propia lista.')`. Decírselo así le explica
     * qué pasó; el texto genérico —«no tienes acceso»— lo dejaría pensando que
     * algo se rompió en su propia lista.
     */
    public function test_a_forbidden_page_uses_its_own_explanation(): void
    {
        [$duenio, $lista] = $this->escena();

        $this->actingAs($duenio)
            ->get(route('gifts.show', $lista))
            ->assertForbidden()
            ->assertSee('Esta es tu propia lista.');
    }

    /**
     * Y cuando no la trae, no se filtra el texto de fábrica de Laravel.
     *
     * Una policy que niega algo lanza «This action is unauthorized.»: en
     * inglés, en una aplicación en español, y sin decir nada que le sirva a
     * nadie.
     */
    public function test_a_forbidden_page_never_shows_the_framework_english(): void
    {
        $ajena = Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => User::factory()->create()->id]);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('wishlists.edit', $ajena))
            ->assertForbidden()
            ->getContent();

        $this->assertStringNotContainsString('This action is unauthorized', $html);
        $this->assertStringContainsString('No tienes acceso a esto', $html);
    }

    /**
     * La lista privada de otro no da un 403 seco: da una puerta con timbre.
     *
     * Es una decisión de diseño, no un descuido —se puede pedir acceso desde
     * ahí— y lo que no puede hacer nunca es nombrar la lista: probando ids
     * cualquiera averiguaría qué listas privadas tiene una persona.
     */
    public function test_a_private_list_shows_a_doorbell_and_never_its_name(): void
    {
        $duenio = User::factory()->create(['is_private' => false, 'name' => 'Ana Rojas', 'show_name' => false]);
        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id, 'name' => 'Regalos secretos de Ana']);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('gifts.show', $lista))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Esta lista es privada', $html);

        foreach (['Regalos secretos de Ana', 'Ana Rojas'] as $rastro) {
            $this->assertStringNotContainsString($rastro, $html, "la puerta filtró «{$rastro}»");
        }
    }

    /**
     * La paginación apagada se anuncia como apagada.
     *
     * Ya lo decía con `aria-disabled`, así que un lector de pantalla lo sabía;
     * lo que faltaba era que se viera, y eso lo hace el CSS a partir de este
     * mismo atributo. Si el atributo se cae, se cae también el estilo.
     */
    public function test_the_pagination_marks_the_dead_end(): void
    {
        $mirando = User::factory()->create();

        // Trece listas públicas de gente distinta: pasan de la primera página.
        foreach (range(1, 13) as $ignorado) {
            $duenio = User::factory()->create(['is_private' => false]);
            Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        }

        $this->actingAs($mirando)
            ->get(route('discover'))
            ->assertOk()
            ->assertSee('aria-disabled="true"', false);
    }
}
