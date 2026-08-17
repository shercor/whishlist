<?php

use App\Enums\WishlistVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «Por enlace» deja de existir y pasa a ser «privada».
     *
     * Desde que toda lista no pública lleva enlace, las dos visibilidades
     * hacían lo mismo: la de enlace era una privada a la que nadie invitaba.
     * Ahora la privada admite las dos formas de repartirse —invitar gente y
     * pasar el enlace— y no hay que elegir entre ellas.
     *
     * Esta migración es obligatoria, no cosmética: el enum ya no tiene el caso
     * `LINK`, así que una fila que se quedara en `por_enlace` haría reventar
     * `fromLabel()` en cuanto alguien abriera esa lista.
     *
     * Nadie pierde acceso: el `share_token` se conserva intacto y `openByLink`
     * busca por token sin mirar la visibilidad, así que los enlaces que ya
     * andan circulando siguen funcionando igual.
     */
    public function up(): void
    {
        DB::table('wishlists')
            ->where('visibility', 'por_enlace')
            ->update(['visibility' => WishlistVisibility::PRIVATE->label()]);
    }

    /**
     * No hay vuelta atrás posible.
     *
     * Después de fusionarlas ya no se puede saber cuáles eran «por enlace» y
     * cuáles nacieron privadas. Convertir todas las privadas de vuelta sería
     * peor que no hacer nada: abriría por enlace listas que nunca lo tuvieron.
     */
    public function down(): void
    {
        // A propósito vacío.
    }
};
