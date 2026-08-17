<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea (o actualiza) el usuario administrador inicial.
     *
     * Es idempotente a propósito: se puede volver a correr sin duplicar el
     * usuario ni fallar, por ejemplo si olvidaste la contraseña.
     */
    public function run(): void
    {
        $email = config('admin.email');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name'),
                // 'admin' está en la lista de reservados a propósito, así que
                // el administrador tampoco lo usa: nadie debe poder hacerse
                // pasar por el sistema, ni siquiera él.
                'username' => 'equipo_whishlist',
                'password' => Hash::make(config('admin.password')),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Administrador listo: {$user->email}");
    }
}
