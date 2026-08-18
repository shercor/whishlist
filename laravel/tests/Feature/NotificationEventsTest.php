<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Notifications\AccessAnswered;
use App\Notifications\FollowAccepted;
use App\Notifications\FollowReceived;
use App\Notifications\WishlistShared;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Los cuatro avisos que faltaban.
 *
 * Cada uno se prueba **por la web y por la API**, y eso es lo importante de este
 * archivo: los avisos se disparan desde los controladores, hay dos juegos de
 * controladores para las mismas acciones, y agregar un aviso en uno y olvidarlo
 * en el otro no rompe nada visible. Es exactamente así como los dos caminos se
 * desincronizan.
 */
class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Wishlist}
     */
    private function duenioYCurioso(): array
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $curioso = User::factory()->create();

        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id]);

        // Pedir una lista exige seguir a su dueño.
        Follow::factory()->between($curioso, $duenio)->accepted()->create();

        return [$duenio, $curioso, $lista];
    }

    private function solicitud(User $curioso, Wishlist $lista): WishlistAccess
    {
        return WishlistAccess::factory()
            ->status(AccessRequestStatus::PENDING)
            ->create([
                'wishlist_id' => $lista->id,
                'user_id' => $curioso->id,
                'source' => AccessSource::REQUEST->label(),
            ]);
    }

    // --- Respondieron tu pedido ----------------------------------------------

    public function test_approving_a_request_tells_the_person_who_asked(): void
    {
        Notification::fake();

        [$duenio, $curioso, $lista] = $this->duenioYCurioso();
        $acceso = $this->solicitud($curioso, $lista);

        $this->actingAs($duenio)->patch(route('access.update', $acceso), [
            'status' => AccessRequestStatus::APPROVED->label(),
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($curioso, AccessAnswered::class);
        // Al dueño no se le avisa de su propia respuesta.
        Notification::assertNotSentTo($duenio, AccessAnswered::class);
    }

    public function test_approving_a_request_through_the_api_tells_them_too(): void
    {
        Notification::fake();

        [$duenio, $curioso, $lista] = $this->duenioYCurioso();
        $acceso = $this->solicitud($curioso, $lista);

        Sanctum::actingAs($duenio);

        $this->patchJson(route('api.v1.access.update', $acceso), [
            'status' => AccessRequestStatus::APPROVED->label(),
        ])->assertOk();

        Notification::assertSentTo($curioso, AccessAnswered::class);
    }

    public function test_a_rejection_is_told_but_never_names_the_list(): void
    {
        [$duenio, $curioso, $lista] = $this->duenioYCurioso();
        $acceso = $this->solicitud($curioso, $lista);

        $this->actingAs($duenio)->patch(route('access.update', $acceso), [
            'status' => AccessRequestStatus::REJECTED->label(),
        ]);

        $datos = $curioso->notifications()->firstOrFail()->data;

        // Nombrar la lista que te negaron sería contar algo de una lista que
        // sigues sin poder ver.
        $this->assertStringNotContainsString($lista->name, json_encode($datos));
        $this->assertStringContainsString('no te dio acceso', $datos['titulo']);
    }

    /**
     * Quitarle a alguien un acceso que ya tenía no se anuncia: avisar de que le
     * quitaste algo no arregla nada y sí incomoda.
     */
    public function test_revoking_an_access_is_not_announced(): void
    {
        Notification::fake();

        [$duenio, $curioso, $lista] = $this->duenioYCurioso();
        $acceso = $this->solicitud($curioso, $lista);

        $this->actingAs($duenio)->patch(route('access.update', $acceso), [
            'status' => AccessRequestStatus::REVOKED->label(),
        ]);

        Notification::assertNothingSentTo($curioso);
    }

    // --- Te compartieron una lista -------------------------------------------

    public function test_an_invitation_is_announced_to_the_invited_person(): void
    {
        Notification::fake();

        [$duenio, $curioso, $lista] = $this->duenioYCurioso();

        $this->actingAs($duenio)->post(route('access.invite', $lista), [
            'user_id' => $curioso->id,
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($curioso, WishlistShared::class);
    }

    public function test_an_invitation_through_the_api_is_announced_too(): void
    {
        Notification::fake();

        [$duenio, $curioso, $lista] = $this->duenioYCurioso();

        Sanctum::actingAs($duenio);

        $this->postJson(route('api.v1.access.invite', $lista), [
            'user_id' => $curioso->id,
        ])->assertCreated();

        Notification::assertSentTo($curioso, WishlistShared::class);
    }

    // --- Te siguen ------------------------------------------------------------

    public function test_a_new_follower_on_a_public_profile_is_announced(): void
    {
        Notification::fake();

        $seguido = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create();

        $this->actingAs($seguidor)->post(route('follows.store', $seguido));

        Notification::assertSentTo($seguido, FollowReceived::class);
    }

    public function test_a_follow_request_on_a_private_profile_is_announced(): void
    {
        $seguido = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();

        $this->actingAs($seguidor)->post(route('follows.store', $seguido));

        $datos = $seguido->notifications()->firstOrFail()->data;

        $this->assertStringContainsString('quiere seguirte', $datos['titulo']);
    }

    /**
     * Doble clic en «seguir»: un aviso, no dos.
     */
    public function test_following_twice_only_announces_once(): void
    {
        $seguido = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create();

        foreach (range(1, 2) as $ignorado) {
            $this->actingAs($seguidor)->post(route('follows.store', $seguido));
        }

        $this->assertSame(1, $seguido->notifications()->count());
    }

    public function test_following_through_the_api_is_announced(): void
    {
        Notification::fake();

        $seguido = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create();

        Sanctum::actingAs($seguidor);

        $this->postJson(route('api.v1.follows.store', $seguido))->assertCreated();

        Notification::assertSentTo($seguido, FollowReceived::class);
    }

    // --- Te aceptaron ---------------------------------------------------------

    public function test_accepting_a_follow_request_tells_the_person_who_asked(): void
    {
        Notification::fake();

        $seguido = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();

        $follow = Follow::factory()->between($seguidor, $seguido)->create();

        $this->actingAs($seguido)->patch(route('follows.update', $follow), [
            'decision' => 'aceptar',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($seguidor, FollowAccepted::class);
    }

    public function test_accepting_through_the_api_tells_them_too(): void
    {
        Notification::fake();

        $seguido = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();

        $follow = Follow::factory()->between($seguidor, $seguido)->create();

        Sanctum::actingAs($seguido);

        $this->patchJson(route('api.v1.follows.update', $follow), [
            'decision' => 'aceptar',
        ])->assertOk();

        Notification::assertSentTo($seguidor, FollowAccepted::class);
    }

    /**
     * Rechazar no se avisa: borra la fila y quien pidió puede volver a
     * intentarlo. Anunciar un rechazo solo sirve para que duela.
     */
    public function test_rejecting_a_follow_request_is_not_announced(): void
    {
        Notification::fake();

        $seguido = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();

        $follow = Follow::factory()->between($seguidor, $seguido)->create();

        $this->actingAs($seguido)->patch(route('follows.update', $follow), [
            'decision' => 'rechazar',
        ]);

        Notification::assertNothingSentTo($seguidor);
    }

    /**
     * Ningún aviso puede filtrar el nombre real de quien lo oculta.
     */
    public function test_no_notification_leaks_a_hidden_real_name(): void
    {
        $seguido = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create([
            'name' => 'Ana Rojas',
            'show_name' => false,
        ]);

        $this->actingAs($seguidor)->post(route('follows.store', $seguido));

        $datos = $seguido->notifications()->firstOrFail()->data;

        $this->assertStringNotContainsString('Ana Rojas', json_encode($datos));
        $this->assertStringContainsString($seguidor->handle(), $datos['titulo']);
    }

    /**
     * Un aviso cuyo modelo desaparece antes de que el worker llegue.
     *
     * Este test no usa la cola síncrona: la empuja a la de base de datos y
     * corre el worker de verdad, porque el fallo que persigue **solo ocurre
     * ahí**. La petición que encola el aviso termina bien, y el job muere
     * después, dentro del worker, donde nadie lo ve hasta mirar `failed_jobs`.
     *
     * Pasaba de verdad: seguir a alguien y dejar de seguirlo en el mismo minuto
     * dejaba un job fallido. Lo resuelve `$deleteWhenMissingModels`.
     */
    public function test_an_announcement_whose_subject_vanished_is_discarded_not_failed(): void
    {
        $seguido = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create();

        $follow = Follow::factory()->between($seguidor, $seguido)->accepted()->create();

        $seguido->notify((new FollowReceived($follow))->onConnection('database'));

        // Se deshace antes de que el worker le llegue el turno.
        $follow->delete();

        // La conexión va explícita: sin ella el worker usa la de por defecto,
        // que en los tests es `sync`, y no encontraría nada que hacer.
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--queue' => 'default',
        ]);

        // Descartado, no fallido: no hay nada que anunciar.
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, $seguido->notifications()->count());
    }

    /**
     * El control del anterior: con el modelo en su sitio, el mismo camino por la
     * cola de verdad sí deja el aviso.
     */
    public function test_the_same_path_does_deliver_when_the_subject_is_still_there(): void
    {
        $seguido = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create();

        $follow = Follow::factory()->between($seguidor, $seguido)->accepted()->create();

        $seguido->notify((new FollowReceived($follow))->onConnection('database'));

        // La conexión va explícita: sin ella el worker usa la de por defecto,
        // que en los tests es `sync`, y no encontraría nada que hacer.
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--queue' => 'default',
        ]);

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, $seguido->notifications()->count());
    }
}
