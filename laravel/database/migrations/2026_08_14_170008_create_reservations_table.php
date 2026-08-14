<?php

use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla separada de wishlist_items a propósito. Si el dato de quién
        // reservó viviera en la fila del ítem, el dueño lo traería sin querer
        // en cualquier select de su propia lista y se arruinaría la sorpresa.
        // Acá llegar a esa información obliga a hacer un join explícito.
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_item_id')->constrained('wishlist_items')->cascadeOnDelete();

            // Quien reserva el regalo. Nunca es el dueño de la lista; eso se
            // valida en la aplicación, porque la BD no conoce esa relación.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 15)->default(ReservationStatus::ACTIVE->label());

            // Reserva con vencimiento: si alguien reserva y nunca compra, el
            // regalo no queda bloqueado para siempre.
            $table->timestamp('expires_at')->nullable();

            // Cuándo dejó de estar activa, sea por compra, cancelación o
            // vencimiento. Junto con status arma el historial.
            $table->timestamp('released_at')->nullable();

            $table->string('note', 300)->nullable();

            $table->timestamps();

            // Un solo regalo reservado a la vez, garantizado por la base y no
            // por la aplicación. El truco: active_flag vale 1 mientras la
            // reserva está viva y NULL cuando termina. Como MariaDB no
            // considera iguales dos NULL en un índice único, caben muchas
            // reservas históricas del mismo ítem pero solo una activa.
            // Evita que dos personas reserven el mismo regalo a la vez.
            $table->unsignedTinyInteger('active_flag')->nullable();
            $table->unique(['wishlist_item_id', 'active_flag'], 'reservations_one_active_per_item');

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
