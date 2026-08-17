<?php

use App\Enums\FollowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién sigue a quién.
     *
     * Existe para resolver un problema concreto: hasta acá, para dar acceso a
     * una lista privada el dueño tenía que elegir entre *todos* los usuarios
     * de la plataforma, lo que obliga a un buscador de personas dentro de cada
     * lista. Con seguidores, los candidatos ya están acotados a gente que el
     * dueño reconoce.
     */
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();

            // Quien sigue.
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            // A quien sigue.
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();

            // Un perfil público acepta al instante; uno privado deja la
            // solicitud pendiente hasta que su dueño responda.
            $table->string('status', 15)->default(FollowStatus::PENDING->label());
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            // Una relación por par, en ese sentido. Seguir es de ida: que A
            // siga a B no dice nada de si B sigue a A.
            $table->unique(['follower_id', 'followed_id']);
            // Las dos consultas que se hacen todo el tiempo: "mis seguidores"
            // y "a quién sigo", siempre filtrando por estado.
            $table->index(['followed_id', 'status']);
            $table->index(['follower_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
