<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * Suelta las reservas cuyo plazo venció. Sin esto, quien reserva y nunca
 * compra deja el regalo bloqueado para siempre.
 */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'reservations:release-expired';

    protected $description = 'Libera las reservas vencidas para que el regalo vuelva a estar disponible';

    public function handle(ReservationService $reservations): int
    {
        $liberadas = $reservations->releaseExpired();

        $this->info($liberadas === 0
            ? 'No había reservas vencidas.'
            : "Reservas liberadas: {$liberadas}.");

        return self::SUCCESS;
    }
}
