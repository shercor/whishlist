<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los «me gusta» del catálogo.
     *
     * No miden si el regalo es lindo, sino si la ficha está bien hecha: buena
     * foto, precio de referencia razonable, nombre claro. Es lo que decide
     * cuál de tres fichas del mismo producto se le muestra primero a la gente,
     * así que la curaduría del catálogo la terminan haciendo los usuarios.
     */
    public function up(): void
    {
        Schema::create('product_likes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Si alguien se da de baja, sus votos se van con él: dejarlos
            // contando sería inflar el ranking con gente que ya no está.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Un voto por persona y producto. Lo garantiza la base, no el
            // botón: dos clics rápidos llegan como dos peticiones.
            $table->unique(['product_id', 'user_id'], 'product_likes_one_per_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_likes');
    }
};
