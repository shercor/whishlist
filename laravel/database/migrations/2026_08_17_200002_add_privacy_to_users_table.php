<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El perfil privado.
     *
     * Un perfil privado se encuentra por su arroba y hasta ahí: no muestra
     * ninguna lista, ni siquiera las marcadas como públicas, hasta que su
     * dueño acepte que lo sigan. Si mostrara las públicas de todos modos,
     * «perfil privado» no querría decir nada.
     *
     * Nace en true —privado— por dos razones: es lo protector, que es la
     * postura del resto del proyecto, y porque el caso común esperado es el
     * de alguien que arma una lista de cumpleaños para su círculo, no para
     * internet.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_private')->default(true)->after('show_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
