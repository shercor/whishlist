<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Convive con image_url, no lo reemplaza: son dos orígenes
            // distintos. image_url es la foto que vive en el sitio de la
            // tienda, que traen los seeders del catálogo; image_path es un
            // archivo que subió una persona y que guardamos nosotros.
            // Cuando están los dos, manda el subido: alguien se tomó el
            // trabajo, y una URL ajena se cae cuando la tienda la borra.
            $table->string('image_path', 2048)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
