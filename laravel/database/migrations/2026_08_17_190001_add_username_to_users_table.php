<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * El identificador público de una persona.
     *
     * Hasta acá la única forma de nombrar a alguien era su `name` —el nombre
     * real— o su correo, y ninguno de los dos sirve: buscar por nombre expone
     * a quien no quiere ser encontrado, y el correo directamente no se muestra.
     * El `username` es lo que se enseña y lo único por lo que se busca.
     *
     * `show_name` nace en false a propósito: si alguien quiere aparecer con su
     * nombre real, que sea porque lo eligió, no porque no revisó la opción.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('name');
            $table->boolean('show_name')->default(false)->after('username');
        });

        $this->backfillUsernames();

        Schema::table('users', function (Blueprint $table) {
            // Recién ahora que todas las filas tienen valor se puede exigir.
            $table->string('username', 30)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'show_name']);
        });
    }

    /**
     * Le inventa un usuario a quien ya estaba registrado.
     *
     * Sale del nombre, no del correo: el correo no se muestra nunca y
     * convertirlo en identificador público lo filtraría de una. Si dos
     * personas se llaman igual, al segundo se le agrega un número.
     */
    private function backfillUsernames(): void
    {
        // Copiada a mano y no leída de User::USERNAMES_RESERVADOS: una
        // migración ya ejecutada no puede cambiar de comportamiento porque
        // alguien edite una constante meses después.
        //
        // Importa: sin esto, alguien llamado «Admin» se quedaba con el usuario
        // «admin», que la validación rechaza. Quedaba con un perfil que no
        // podía guardar nunca.
        $tomados = [
            'admin', 'administrador', 'root', 'api', 'login', 'logout', 'register',
            'wishlists', 'discover', 'reservations', 'access', 'products', 'users',
            'perfil', 'profile', 'whishlist', 'soporte', 'ayuda', 'null', 'undefined',
        ];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $usuario) {
            $base = Str::of($usuario->name)
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->limit(24, '')
                ->toString();

            // Un nombre que no deja ni una letra utilizable —solo símbolos, u
            // otro alfabeto— igual necesita algo con qué entrar.
            if (strlen($base) < 3) {
                $base = 'usuario'.$usuario->id;
            }

            // El primer candidato también se compara contra los reservados,
            // así que «Admin» pasa a ser «admin2» en vez de chocar.
            $candidato = $base;
            $sufijo = 1;

            while (in_array($candidato, $tomados, true)) {
                $candidato = $base.(++$sufijo);
            }

            $tomados[] = $candidato;

            DB::table('users')->where('id', $usuario->id)->update(['username' => $candidato]);
        }
    }
};
