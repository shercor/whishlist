<?php

use App\Enums\AccessSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Por dónde entró cada quien.
     *
     * Antes solo existía un camino —pedir y que te aprueben— así que no hacía
     * falta distinguirlos. Ahora hay tres y no duran lo mismo: el invitado y
     * el que pidió dependen de seguir al dueño, el que llegó con el enlace no.
     *
     * Además el enlace deja de vivir solo en la sesión y pasa a quedar
     * anotado acá. Ese es el cambio que hace que el dueño pueda ver quién
     * entró a su lista y echar a alguien: un acceso que solo existe en la
     * sesión del visitante es invisible e irrevocable.
     */
    public function up(): void
    {
        Schema::table('wishlist_accesses', function (Blueprint $table) {
            $table->string('source', 15)
                ->default(AccessSource::REQUEST->label())
                ->after('status');
        });

        // Todo lo que ya existía llegó por el único camino que había.
        DB::table('wishlist_accesses')->update(['source' => AccessSource::REQUEST->label()]);

        Schema::table('wishlist_accesses', function (Blueprint $table) {
            $table->index(['wishlist_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_accesses', function (Blueprint $table) {
            $table->dropIndex(['wishlist_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
