<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Datos de referencia: van en cualquier entorno, producción incluida.
        $this->call([
            AdminUserSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            ProductSeeder::class,
        ]);

        // Datos de ejemplo: solo en desarrollo. Nunca queremos usuarios de
        // prueba con contraseña conocida en un servidor real.
        if (app()->environment('local', 'testing')) {
            $this->call(DemoSeeder::class);
        }
    }
}
