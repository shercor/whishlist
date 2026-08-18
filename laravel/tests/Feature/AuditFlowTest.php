<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Models\WishlistItem;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sonda de auditoría, segunda parte: los cambios de estado.
 *
 * No prueba una pantalla sino una secuencia —reservar y que borren la lista,
 * volver pública una lista privada, quitarle el acceso a alguien— porque es
 * donde quedan los restos: filas vivas apuntando a cosas que ya no existen y
 * permisos que sobreviven al motivo por el que se dieron.
 */
class AuditFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El enlace secreto tiene que morir al volver pública la lista, y el que
     * nazca después debe ser otro. Si se reciclara, quien tuvo el enlace una
     * vez volvería a entrar sin que nadie se lo diera.
     */
    public function test_the_secret_link_dies_when_the_list_becomes_public(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id]);

        $tokenViejo = $lista->share_token;
        $this->assertNotNull($tokenViejo);

        $this->actingAs($duenio)->put(route('wishlists.update', $lista), [
            'name' => $lista->name,
            'visibility' => WishlistVisibility::PUBLIC->label(),
        ])->assertSessionHasNoErrors();

        $this->assertNull($lista->fresh()->share_token);

        // Y de vuelta a privada: token nuevo, no el de antes.
        $this->actingAs($duenio)->put(route('wishlists.update', $lista), [
            'name' => $lista->name,
            'visibility' => WishlistVisibility::PRIVATE->label(),
        ])->assertSessionHasNoErrors();

        $tokenNuevo = $lista->fresh()->share_token;

        $this->assertNotNull($tokenNuevo);
        $this->assertNotSame($tokenViejo, $tokenNuevo);

        // El enlace viejo ya no abre nada.
        $extranio = User::factory()->create();
        $this->actingAs($extranio)
            ->get(route('shared.open', ['token' => $tokenViejo]))
            ->assertNotFound();
    }

    /**
     * Quitarle el acceso a alguien tiene que cerrarle la puerta en el acto.
     */
    public function test_revoking_an_access_closes_the_list_immediately(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $invitado = User::factory()->create();
        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id]);

        // El invitado sigue al dueño: un acceso por invitación exige que la
        // relación siga en pie, no solo que el permiso se haya dado una vez.
        Follow::factory()->between($invitado, $duenio)->accepted()->create();

        // Invitación aprobada: es el camino por el que alguien entra a una
        // lista privada sin haberla pedido.
        $acceso = WishlistAccess::factory()
            ->status(AccessRequestStatus::APPROVED)
            ->create([
                'wishlist_id' => $lista->id,
                'user_id' => $invitado->id,
                'source' => AccessSource::INVITATION->label(),
            ]);

        $this->assertTrue($invitado->can('view', $lista));

        $this->actingAs($duenio)
            ->delete(route('access.revoke', [$lista, $acceso]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($invitado->fresh()->can('view', $lista->fresh()));
    }

    /**
     * Marcar «ya me llegó» es un interruptor y tiene que volver atrás: si solo
     * fuera de ida, un clic por error dejaría el regalo fuera de la lista para
     * siempre.
     */
    public function test_marking_received_can_be_undone(): void
    {
        $duenio = User::factory()->create();
        $item = WishlistItem::factory()->for(
            Wishlist::factory()->create(['user_id' => $duenio->id])
        )->create();

        $this->actingAs($duenio)->post(route('items.received', $item));
        $this->assertNotNull($item->fresh()->received_at);

        $this->actingAs($duenio)->post(route('items.received', $item));
        $this->assertNull($item->fresh()->received_at);
    }

    /**
     * Si el dueño borra la lista, la reserva de otro se va con ella. Lo que no
     * puede pasar es que «Voy a regalar» quede apuntando al vacío y reviente.
     */
    public function test_deleting_a_list_does_not_break_the_reservers_page(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $regalador = User::factory()->create();

        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->actingAs($duenio)->delete(route('wishlists.destroy', $lista));

        $this->actingAs($regalador)
            ->get(route('reservations.index'))
            ->assertOk();

        // La reserva se suelta, no se borra: el historial de un regalo se
        // conserva, y solo el `active_flag` decide si sigue bloqueándolo.
        $this->assertSame(0, $regalador->reservations()->active()->count());
        $this->assertSame(1, $regalador->reservations()->count());
    }

    /**
     * Lo mismo, borrando solo el regalo.
     */
    public function test_deleting_a_gift_does_not_break_the_reservers_page(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $regalador = User::factory()->create();

        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->actingAs($duenio)->delete(route('items.destroy', $item));

        $this->actingAs($regalador)->get(route('reservations.index'))->assertOk();
    }

    /**
     * El dueño marca un regalo como recibido mientras alguien lo tenía
     * reservado. La reserva de esa persona no debería quedar viva bloqueando
     * un regalo que ya no se ofrece.
     */
    public function test_marking_received_does_not_leave_a_live_reservation_behind(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $regalador = User::factory()->create();

        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        $reserva = Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->actingAs($duenio)->post(route('items.received', $item));

        $this->assertFalse(
            $reserva->fresh()->isActive(),
            'El regalo se marcó recibido pero la reserva sigue viva.'
        );
    }

    /**
     * Nadie se sigue a sí mismo ni se pide su propia lista.
     */
    public function test_you_cannot_follow_or_ask_yourself(): void
    {
        $yo = User::factory()->create();
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $yo->id]);

        $this->actingAs($yo)->post(route('follows.store', $yo))->assertForbidden();
        $this->actingAs($yo)->post(route('access.store', $lista))->assertForbidden();
    }

    /**
     * Los usuarios reservados por el sistema no se pueden registrar: chocarían
     * con una ruta.
     */
    public function test_reserved_usernames_cannot_be_registered(): void
    {
        foreach (['admin', 'login', 'wishlists'] as $reservado) {
            $this->post(route('register'), [
                'name' => 'Quien Sea',
                'username' => $reservado,
                'email' => "{$reservado}@ejemplo.cl",
                'password' => 'secreta123',
                'password_confirmation' => 'secreta123',
            ])->assertSessionHasErrors('username');
        }

        $this->assertSame(0, User::whereIn('username', ['admin', 'login', 'wishlists'])->count());
    }

    /**
     * Una solicitud rechazada no abre nada, y volver a pedir no puede duplicar
     * la fila ni reventar contra el único de (wishlist_id, user_id).
     */
    public function test_a_rejected_request_neither_opens_nor_duplicates(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $curioso = User::factory()->create();
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);

        $this->actingAs($curioso)->post(route('follows.store', $duenio));
        $this->actingAs($curioso)->post(route('access.store', $lista));

        $acceso = $lista->accesses()->firstOrFail();

        $this->actingAs($duenio)->patch(route('access.update', $acceso), [
            'status' => AccessRequestStatus::REJECTED->label(),
        ]);

        $this->assertFalse($curioso->fresh()->can('view', $lista->fresh()));

        // Vuelve a pedir: no debe ser un 500 por la restricción única.
        $respuesta = $this->actingAs($curioso)->post(route('access.store', $lista));

        $this->assertLessThan(500, $respuesta->status());
        $this->assertSame(1, $lista->accesses()->count());
    }

    /**
     * El aviso de vencimiento sobre una reserva huérfana.
     *
     * Se prueba a la fuerza porque hoy no debería poder existir —al borrar el
     * regalo se suelta la reserva—, pero si una se colara, el fallo ocurriría
     * **dentro del worker**: la petición que encola el aviso termina bien y
     * nadie se entera hasta mirar `failed_jobs`. Pasó de verdad.
     */
    public function test_a_warning_is_never_built_for_a_gift_that_no_longer_exists(): void
    {
        $regalador = User::factory()->create();
        $item = WishlistItem::factory()->create();

        $reserva = Reservation::factory()
            ->for($item, 'wishlistItem')
            ->for($regalador)
            ->create(['expires_at' => now()->addDay()]);

        // Se borra el regalo por debajo, sin pasar por el hook que sueltaría
        // la reserva: así queda exactamente la fila que rompía el job.
        WishlistItem::where('id', $item->id)->update(['deleted_at' => now()]);
        $reserva->update(['active_flag' => 1]);

        $avisadas = app(ReservationService::class)->warnExpiring();

        $this->assertSame(0, $avisadas);
        $this->assertSame(0, $regalador->notifications()->count());
    }
}
