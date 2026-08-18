<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo se avisó a quien reservó que su plazo estaba por vencer.
 *
 * Existe para no avisar dos veces. El comando corre a diario y la ventana de
 * aviso dura varios días, así que sin esta marca la misma reserva generaría
 * una notificación por día hasta vencer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('expiry_warned_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('expiry_warned_at');
        });
    }
};
