<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cada hora basta: el plazo de una reserva se mide en días, no en minutos.
Schedule::command('reservations:release-expired')->hourly();

// Cada hora, como la de vencidas: quien deja de seguir a alguien deja el
// regalo bloqueado hasta que esto pase, y una hora es despreciable al lado de
// los 14 días que duraba antes.
Schedule::command('reservations:release-unreachable')->hourly();

// Una vez al día y a media mañana: el aviso dice «te quedan 3 días», así que
// llegar a las 3 de la madrugada solo conseguiría que se leyera más tarde.
// Va antes que la liberación del día para no avisar de algo ya soltado.
Schedule::command('reservations:warn-expiring')->dailyAt('10:00');
