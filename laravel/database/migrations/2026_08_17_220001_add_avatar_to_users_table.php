<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La foto de perfil.
     *
     * Va al disco `public` con nombre aleatorio, igual que las fotos de los
     * productos. Es una foto que la persona eligió mostrar a quien la
     * encuentre, así que no necesita más protección que esa.
     *
     * Queda nullable para siempre: nadie está obligado a poner cara, y la
     * interfaz dibuja un placeholder con sus iniciales cuando no hay.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path', 2048)->nullable()->after('show_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
