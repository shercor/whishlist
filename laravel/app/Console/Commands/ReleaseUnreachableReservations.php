<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

/**
 * Suelta las reservas de quien ya no alcanza la lista.
 *
 * Existe porque una reserva es un hecho guardado que bloquea el regalo para los
 * demás, y eso no se puede resolver preguntando en el momento de mirar —que es
 * como el resto de la aplicación resuelve los permisos—: quien perdió el acceso
 * ya no vuelve a mirar nunca.
 */
class ReleaseUnreachableReservations extends Command
{
    protected $signature = 'reservations:release-unreachable';

    protected $description = 'Suelta las reservas de quien ya no puede ver la lista del regalo';

    public function handle(ReservationService $reservations): int
    {
        $soltadas = $reservations->releaseUnreachable();

        $this->info($soltadas === 0
            ? 'No había reservas fuera de alcance.'
            : "Reservas soltadas: {$soltadas}.");

        return self::SUCCESS;
    }
}
