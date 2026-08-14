<?php

use App\Enums\AccessRequestStatus;
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
        Schema::create('wishlist_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained('wishlists')->cascadeOnDelete();

            // Quien pide ver la lista.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 15)->default(AccessRequestStatus::PENDING->label());

            // Mensaje opcional al pedir acceso ("soy la tía Marta").
            $table->string('message', 300)->nullable();

            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            // Una sola solicitud por persona y lista: si la rechazan y vuelve a
            // pedir, se reutiliza la fila en vez de acumular historial.
            $table->unique(['wishlist_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['wishlist_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_accesses');
    }
};
