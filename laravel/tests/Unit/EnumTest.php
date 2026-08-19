<?php

namespace Tests\Unit;

use App\Enums\AccessRequestStatus;
use App\Enums\ItemPriority;
use App\Enums\ReservationStatus;
use App\Enums\WishlistVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Los enums son puros y su label() es lo que termina guardado en la base como
 * varchar. Si un label cambia sin migrar los datos, las filas viejas dejan de
 * poder convertirse de vuelta: por eso el ida y vuelta se prueba.
 */
class EnumTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function enums(): array
    {
        return [
            'prioridad' => [ItemPriority::class],
            'visibilidad' => [WishlistVisibility::class],
            'reserva' => [ReservationStatus::class],
            'solicitud' => [AccessRequestStatus::class],
        ];
    }

    #[DataProvider('enums')]
    public function test_every_label_can_be_turned_back_into_its_case(string $enum): void
    {
        foreach ($enum::cases() as $case) {
            $this->assertSame($case, $enum::fromLabel($case->label()));
        }
    }

    #[DataProvider('enums')]
    public function test_labels_are_unique_and_fit_the_column(string $enum): void
    {
        $labels = $enum::labels();

        $this->assertSame($labels, array_values(array_unique($labels)));

        foreach ($labels as $label) {
            // Las columnas de estado son varchar(15) y varchar(10) el de
            // prioridad; 10 es el margen seguro para todas.
            $this->assertLessThanOrEqual(10, mb_strlen($label));
        }
    }

    #[DataProvider('enums')]
    public function test_an_unknown_label_is_rejected(string $enum): void
    {
        $this->expectException(\ValueError::class);

        $enum::fromLabel('no_existe');
    }

    public function test_only_an_active_reservation_blocks_the_item(): void
    {
        $this->assertTrue(ReservationStatus::ACTIVE->blocksItem());

        // Todos los demás, sin enumerarlos: un estado nuevo que bloqueara el
        // ítem sin querer es exactamente lo que este test debe pillar, y una
        // lista escrita a mano no lo pilla porque no lo menciona.
        foreach (ReservationStatus::cases() as $status) {
            if ($status === ReservationStatus::ACTIVE) {
                continue;
            }

            $this->assertFalse($status->blocksItem(), "{$status->label()} bloquea el ítem");
        }
    }

    public function test_only_an_approved_request_grants_access(): void
    {
        $this->assertTrue(AccessRequestStatus::APPROVED->grantsAccess());

        foreach ([AccessRequestStatus::PENDING, AccessRequestStatus::REJECTED, AccessRequestStatus::REVOKED] as $status) {
            $this->assertFalse($status->grantsAccess());
        }

        $this->assertTrue(AccessRequestStatus::PENDING->isAwaitingResponse());
        $this->assertFalse(AccessRequestStatus::REVOKED->isAwaitingResponse());
    }

    /**
     * Toda lista que no sea pública lleva enlace, la privada incluida: es la
     * puerta de quien no sigue al dueño y por tanto no puede ser invitado.
     * La pública no lo necesita porque no hay nada que abrir.
     */
    public function test_every_non_public_wishlist_needs_a_share_token(): void
    {
        $this->assertTrue(WishlistVisibility::PRIVATE->needsShareToken());
        $this->assertFalse(WishlistVisibility::PUBLIC->needsShareToken());

        // Solo la privada exige que el dueño apruebe.
        $this->assertFalse(WishlistVisibility::PRIVATE->isReachableWithoutApproval());
        $this->assertTrue(WishlistVisibility::PUBLIC->isReachableWithoutApproval());
    }

    /**
     * Son dos y nada más. Si alguien agrega un tercer nivel «por enlace», que
     * este test le recuerde por qué se eliminó: la privada ya se comparte por
     * enlace, así que sería el mismo caso con otro nombre.
     */
    public function test_a_wishlist_is_public_or_private_and_nothing_else(): void
    {
        $this->assertSame(
            ['publica', 'privada'],
            WishlistVisibility::labels()
        );
    }

    public function test_priority_weight_sorts_from_most_wanted_to_least(): void
    {
        $this->assertGreaterThan(ItemPriority::MEDIUM->weight(), ItemPriority::HIGH->weight());
        $this->assertGreaterThan(ItemPriority::LOW->weight(), ItemPriority::MEDIUM->weight());
    }
}
