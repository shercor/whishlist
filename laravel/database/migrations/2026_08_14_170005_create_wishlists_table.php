<?php

use App\Enums\WishlistVisibility;
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
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('description', 500)->nullable();

            // Nace privada a propósito: si alguien no elige, el default no
            // debe exponer nada.
            $table->string('visibility', 15)->default(WishlistVisibility::PRIVATE->label());

            // Token para compartir por enlace sin exponer ids correlativos
            // (con /wishlists/7 cualquiera prueba 8, 9, 10...).
            $table->string('share_token', 32)->nullable()->unique();

            // Cumpleaños, navidad, matrimonio. Sirve para ordenar y avisar.
            $table->date('event_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'visibility']);
            $table->index('event_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
