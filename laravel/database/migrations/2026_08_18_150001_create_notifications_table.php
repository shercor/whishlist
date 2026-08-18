<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla estándar de notificaciones de Laravel.
 *
 * `data` guarda un json con lo ya resuelto —el texto, el enlace— y no ids que
 * haya que volver a consultar al pintarlas. Es a propósito: una notificación
 * cuenta algo que pasó, y debe seguir diciendo lo mismo aunque después se
 * borre la lista o el regalo del que habla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // La campana pregunta «cuántas sin leer tiene esta persona» en
            // cada página que se pinta. Sin este índice, esa cuenta recorre
            // las notificaciones de todo el mundo.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
