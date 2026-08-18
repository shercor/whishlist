<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * Avisa a quien reservó que su plazo está por vencer.
 *
 * Corre antes que `reservations:release-expired` en el día: sin este aviso,
 * ese comando le suelta el regalo a alguien que iba a comprarlo y se entera
 * cuando vuelve y ya lo tomó otro.
 */
class WarnExpiringReservations extends Command
{
    protected $signature = 'reservations:warn-expiring';

    protected $description = 'Avisa a quien reservó que a su reserva le quedan pocos días';

    public function handle(ReservationService $reservations): int
    {
        $avisadas = $reservations->warnExpiring();

        $this->info($avisadas === 0
            ? 'No había reservas por vencer.'
            : "Avisos enviados: {$avisadas}.");

        return self::SUCCESS;
    }
}
