<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\ReservationStatus;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Models\WishlistItem;
use App\Notifications\ReservationExpiring;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Los bordes del barrido de reservas fuera de alcance.
 *
 * `UnreachableReservationTest` cubre el caso central: por qué caminos se pierde
 * el acceso y qué pasa entonces. Este cubre lo que salió de auditar ese
 * barrido, que es otra cosa: qué pasa cuando el barrido corre sobre datos que
 * no son los que esperaba, y qué le cuesta.
 *
 * Los cinco son huecos que estaban abiertos y se arreglaron. Están acá para
 * que no vuelvan.
 */
class ReservationSweepHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function barrer(): int
    {
        return app(ReservationService::class)->releaseUnreachable();
    }

    /**
     * Una lista privada con alguien invitado que sigue al dueño, y un regalo
     * suyo reservado.
     *
     * @return array{0: User, 1: User, 2: Wishlist, 3: WishlistItem, 4: Reservation}
     */
    private function escena(): array
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $regalador = User::factory()->create();
        Follow::factory()->between($regalador, $duenio)->accepted()->create();

        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        WishlistAccess::factory()->status(AccessRequestStatus::APPROVED)->create([
            'wishlist_id' => $lista->id,
            'user_id' => $regalador->id,
            'source' => AccessSource::INVITATION->label(),
        ]);

        $reserva = Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->assertTrue($regalador->can('view', $lista));

        return [$duenio, $regalador, $lista, $item, $reserva];
    }

    /**
     * Le quita a esta persona todo lo que la dejaba entrar a la lista.
     */
    private function quitarleElAcceso(Wishlist $lista, User $regalador): void
    {
        $lista->accesses()->where('user_id', $regalador->id)->delete();
        Follow::query()->where('follower_id', $regalador->id)->delete();
    }

    /**
     * El barrido decide por otras personas, así que no puede mirar la sesión.
     *
     * `WishlistPolicy::view()` mezcla dos cosas: lo que tienes concedido y lo
     * que *esta sesión* abrió con el enlace. Preguntando `view()` en nombre de
     * un tercero, una lista que yo abrí con el enlace parecía alcanzable para
     * todo el mundo, y sus reservas se salvaban del barrido sin motivo.
     *
     * En consola no hay sesión y no se notaba. Se notaría el día que alguien
     * llame al barrido desde una petición —que es justo lo que propone el
     * HANDOFF para hacerlo instantáneo—.
     */
    public function test_a_link_open_in_this_session_does_not_save_someone_elses_reservation(): void
    {
        [, $regalador, $lista, , $reserva] = $this->escena();

        $this->quitarleElAcceso($lista, $regalador);
        $this->assertFalse($regalador->fresh()->can('viewDurably', $lista->fresh()));

        // Alguien —cualquiera— abre esa lista con el enlace en esta sesión.
        $lista->unlockByLink();

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    /**
     * Y al revés: quien de verdad entró por el enlace no depende de la sesión
     * para conservar su reserva, porque el acceso quedó anotado en la base.
     */
    public function test_a_link_access_is_honoured_without_any_session(): void
    {
        [, $regalador, $lista, , $reserva] = $this->escena();

        Follow::query()->where('follower_id', $regalador->id)->delete();
        $lista->accesses()->where('user_id', $regalador->id)->update([
            'source' => AccessSource::LINK->label(),
        ]);

        $this->assertSame(0, $this->barrer());
        $this->assertTrue($reserva->fresh()->isActive());
    }

    /**
     * Una reserva colgando de una lista borrada a mano.
     *
     * Los hooks de `Wishlist` y `WishlistItem` sueltan las reservas al borrar,
     * pero solo si el borrado pasa por el modelo. Un update masivo o SQL
     * directo deja la reserva viva bloqueando un ítem que ya nadie ve, y el
     * barrido la **descartaba** —su `whereHas` pedía que la lista existiera—,
     * así que no volvía a mirarla nunca. Ya hizo falta una migración de una
     * vez para limpiar las que había; esto evita necesitar la siguiente.
     */
    public function test_the_sweep_frees_a_reservation_stranded_under_a_deleted_list(): void
    {
        [, , $lista, , $reserva] = $this->escena();

        DB::table('wishlists')->where('id', $lista->id)->update(['deleted_at' => now()]);

        $this->assertTrue($reserva->fresh()->isActive());
        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    /**
     * Lo mismo con el regalo en vez de la lista.
     */
    public function test_the_sweep_frees_a_reservation_stranded_under_a_deleted_gift(): void
    {
        [, , , $item, $reserva] = $this->escena();

        DB::table('wishlist_items')->where('id', $item->id)->update(['deleted_at' => now()]);

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    /**
     * A la huérfana se la suelta en silencio: el aviso nombra el regalo y de
     * quién es, y ahí no hay ninguna de las dos cosas que nombrar. Es lo mismo
     * que ya hacían los hooks de borrado.
     */
    public function test_a_stranded_reservation_is_freed_without_telling_anyone(): void
    {
        Notification::fake();

        [, $regalador, $lista] = $this->escena();

        DB::table('wishlists')->where('id', $lista->id)->update(['deleted_at' => now()]);
        $this->barrer();

        Notification::assertNothingSentTo($regalador);
    }

    /**
     * Quien perdió el acceso no soltó nada: se lo soltaron.
     *
     * Guardarlo como «cancelada» era decirle en «Voy a regalar» que ella lo
     * canceló, y quedarse pensando que se equivocó de botón.
     */
    public function test_a_revoked_reservation_is_not_recorded_as_cancelled(): void
    {
        [, $regalador, $lista, , $reserva] = $this->escena();

        $this->quitarleElAcceso($lista, $regalador);
        $this->barrer();

        $this->assertSame(ReservationStatus::REVOKED, $reserva->fresh()->statusEnum());
        $this->assertSame('Liberada', $reserva->fresh()->statusEnum()->title());
    }

    /**
     * El estado nuevo no bloquea el ítem, que es lo único que la aplicación le
     * pide a un estado terminado.
     */
    public function test_the_revoked_status_does_not_block_the_gift(): void
    {
        $this->assertFalse(ReservationStatus::REVOKED->blocksItem());
        $this->assertSame(ReservationStatus::REVOKED, ReservationStatus::fromLabel('revocada'));
    }

    /**
     * No se avisa «te quedan 3 días» de una reserva que el barrido está a
     * punto de soltar.
     *
     * Antes esto dependía de que el barrido corriera primero, y los dos
     * comandos están programados a la misma hora: el orden lo decidía el orden
     * de registro en `routes/console.php`. Es un hilo muy fino del que colgar
     * un mensaje equivocado.
     */
    public function test_no_expiry_warning_goes_out_for_a_reservation_you_cannot_reach(): void
    {
        Notification::fake();

        [, $regalador, $lista, , $reserva] = $this->escena();

        $reserva->update(['expires_at' => now()->addDay()]);
        $this->quitarleElAcceso($lista, $regalador);

        $this->assertSame(0, app(ReservationService::class)->warnExpiring());
        Notification::assertNothingSentTo($regalador);

        // Y no se marca como avisada: si recupera el acceso, el aviso todavía
        // le sirve.
        $this->assertNull($reserva->fresh()->expiry_warned_at);
    }

    /**
     * Quien sí alcanza la lista sigue recibiendo su aviso.
     */
    public function test_the_expiry_warning_still_goes_out_when_you_can_reach_the_list(): void
    {
        Notification::fake();

        [, $regalador, , , $reserva] = $this->escena();

        $reserva->update(['expires_at' => now()->addDay()]);

        $this->assertSame(1, app(ReservationService::class)->warnExpiring());
        Notification::assertSentTo($regalador, ReservationExpiring::class);
    }

    /**
     * Lo que cuesta el barrido.
     *
     * `with()` seguido de `cursor()` no carga nada: cursor() ignora el eager
     * loading y vuelve a la base por cada relación de cada reserva. Eran seis
     * consultas por reserva en un comando que corre cada hora. Con `lazy()`
     * quedan las dos que gasta la policy —el acceso anotado y el seguimiento—,
     * que son las que no se pueden evitar sin repetir sus reglas en un `where`.
     */
    public function test_the_sweep_does_not_query_once_per_relation(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->escena();
        }

        DB::enableQueryLog();
        $this->barrer();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 5 personas y 5 listas distintas: 2 por pareja, más las de arranque.
        $this->assertLessThan(18, $consultas, "el barrido gastó {$consultas} consultas para 5 reservas");
    }

    /**
     * Tres regalos de la misma lista reservados por la misma persona son una
     * sola pregunta a la policy, no tres.
     */
    public function test_the_verdict_is_reused_for_the_same_person_and_list(): void
    {
        [, $regalador, $lista] = $this->escena();

        foreach (range(1, 2) as $ignorado) {
            $item = WishlistItem::factory()->for($lista)->create();
            Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();
        }

        DB::enableQueryLog();
        $this->barrer();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(8, $consultas, "3 reservas de una misma pareja costaron {$consultas} consultas");
    }

    /**
     * Y cuando esa pareja pierde el acceso, se sueltan las tres y se avisa una
     * vez por reserva —no una vez por lista—: cada regalo es una compra que la
     * persona podría estar por hacer.
     */
    public function test_every_reservation_of_the_same_list_is_freed(): void
    {
        Notification::fake();

        [, $regalador, $lista] = $this->escena();

        foreach (range(1, 2) as $ignorado) {
            $item = WishlistItem::factory()->for($lista)->create();
            Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();
        }

        $this->quitarleElAcceso($lista, $regalador);

        $this->assertSame(3, $this->barrer());
        $this->assertSame(0, Reservation::query()->whereNotNull('active_flag')->count());
        Notification::assertSentToTimes($regalador, \App\Notifications\ReservationReleased::class, 3);
    }
}
