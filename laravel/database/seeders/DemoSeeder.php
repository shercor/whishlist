<?php

namespace Database\Seeders;

use App\Enums\AccessRequestStatus;
use App\Enums\ItemPriority;
use App\Enums\ReservationStatus;
use App\Enums\WishlistVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Datos de ejemplo para desarrollo. Arma un escenario que cubre los casos
     * que hacen interesante el dominio: los tres niveles de visibilidad, una
     * solicitud de acceso en cada estado, un producto privado, un regalo ya
     * recibido y un ítem que se reservó, se soltó y se volvió a reservar.
     */
    public function run(): void
    {
        $ana = $this->user('Ana Rojas', 'ana@whishlist.test');
        $bruno = $this->user('Bruno Díaz', 'bruno@whishlist.test');
        $camila = $this->user('Camila Soto', 'camila@whishlist.test');
        $diego = $this->user('Diego Fuentes', 'diego@whishlist.test');

        // --- Listas de Ana -------------------------------------------------

        $cumple = $this->wishlist($ana, 'Mi cumpleaños 2026', WishlistVisibility::PUBLIC, [
            'description' => 'Cumplo el 20 de septiembre. Cualquier cosa de acá me hace feliz.',
            'event_date' => '2026-09-20',
        ]);

        $navidad = $this->wishlist($ana, 'Navidad en familia', WishlistVisibility::LINK, [
            'description' => 'Para el amigo secreto de este año.',
            'event_date' => '2026-12-24',
        ]);

        $casa = $this->wishlist($ana, 'Cosas para la casa', WishlistVisibility::PRIVATE, [
            'description' => 'Mi lista personal, no la ando compartiendo.',
        ]);

        // --- Listas de otros ----------------------------------------------

        $brunoList = $this->wishlist($bruno, 'Lo que ando buscando', WishlistVisibility::PUBLIC);
        $this->wishlist($camila, 'Regalos de matrimonio', WishlistVisibility::LINK, [
            'event_date' => '2026-11-08',
        ]);

        // --- Ítems ---------------------------------------------------------

        $pikachu = $this->item($cumple, 'Peluche Pikachu 30 cm', ItemPriority::HIGH, [
            'notes' => 'El oficial, no la copia. Ojalá el de 30 cm.',
            'position' => 1,
        ]);

        $audifonos = $this->item($cumple, 'Audífonos Sony WH-1000XM5', ItemPriority::HIGH, [
            'notes' => 'Negros si se puede.',
            'position' => 2,
        ]);

        $catan = $this->item($cumple, 'Catan', ItemPriority::MEDIUM, [
            'notes' => 'La edición base basta, después vemos las expansiones.',
            'position' => 3,
        ]);

        // Regalo que Ana ya recibió: deja de ofrecerse a los demás.
        $this->item($cumple, 'Café de especialidad 1 kg', ItemPriority::LOW, [
            'position' => 4,
            'received_at' => now()->subDays(3),
        ]);

        // Estos dos quedan libres a propósito: son los que vería disponibles
        // alguien que entra a regalarle algo a Ana.
        $this->item($cumple, 'Dune', ItemPriority::MEDIUM, ['position' => 5]);
        $this->item($cumple, 'Vinilo OK Computer - Radiohead', ItemPriority::LOW, [
            'notes' => 'Si es la reedición de 180 g, mejor.',
            'position' => 6,
        ]);

        $this->item($navidad, 'Dixit', ItemPriority::MEDIUM, ['position' => 1]);
        $this->item($navidad, 'Set de acuarelas 24 colores', ItemPriority::LOW, ['position' => 2]);

        $this->item($casa, 'Set de cuchillos japoneses', ItemPriority::MEDIUM, ['position' => 1]);
        $this->item($casa, 'Cafetera italiana Bialetti 6 tazas', ItemPriority::HIGH, ['position' => 2]);

        $this->item($brunoList, 'Nintendo Switch OLED', ItemPriority::HIGH, ['position' => 1]);
        $this->item($brunoList, 'Zapatillas Nike Pegasus 41', ItemPriority::MEDIUM, ['position' => 2]);

        // Producto privado: Ana lo escribió a mano, no está en el catálogo y
        // nadie más lo encuentra buscando.
        $taza = $this->privateProduct($ana, 'Taza de greca de la feria de Valparaíso', 'hogar',
            'Esa azul con blanco que vimos en el paseo. No sé la marca.');
        $this->itemFromProduct($casa, $taza, ItemPriority::LOW, ['position' => 3]);

        // --- Solicitudes de acceso a la lista privada de Ana ----------------

        $this->access($casa, $bruno, AccessRequestStatus::APPROVED, 'Soy Bruno, tu primo.');
        $this->access($casa, $camila, AccessRequestStatus::PENDING, '¿Me dejas ver? Quiero regalarte algo.');
        $this->access($casa, $diego, AccessRequestStatus::REJECTED, 'Hola, ábreme.');

        // --- Reservas -------------------------------------------------------

        // Bruno tiene tomados los audífonos. Ana no debe enterarse.
        $this->reservation($audifonos, $bruno, ReservationStatus::ACTIVE, [
            'expires_at' => now()->addDays(14),
            'note' => 'Lo compro el fin de semana.',
        ]);

        // Camila había reservado el Catan y lo soltó...
        $this->reservation($catan, $camila, ReservationStatus::CANCELLED, [
            'released_at' => now()->subDay(),
            'note' => 'Al final me decidí por otra cosa.',
        ]);

        // ...y por eso Diego pudo reservarlo después. Que ambas filas convivan
        // es justo lo que valida el índice único parcial.
        $this->reservation($catan, $diego, ReservationStatus::ACTIVE, [
            'expires_at' => now()->addDays(7),
        ]);

        // Reserva vencida: queda activa pero con el plazo pasado, para probar
        // el job que las libera.
        $this->reservation($pikachu, $camila, ReservationStatus::ACTIVE, [
            'expires_at' => now()->subDays(2),
        ]);

        $this->report();
    }

    private function user(string $name, string $email): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function wishlist(User $user, string $name, WishlistVisibility $visibility, array $extra = []): Wishlist
    {
        return Wishlist::updateOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            array_merge([
                'visibility' => $visibility->label(),
                // Solo las de enlace necesitan token.
                'share_token' => $visibility->needsShareToken() ? Str::random(32) : null,
            ], $extra)
        );
    }

    private function item(Wishlist $wishlist, string $productName, ItemPriority $priority, array $extra = []): WishlistItem
    {
        $product = Product::where('name', $productName)->firstOrFail();

        return $this->itemFromProduct($wishlist, $product, $priority, $extra);
    }

    private function itemFromProduct(Wishlist $wishlist, Product $product, ItemPriority $priority, array $extra = []): WishlistItem
    {
        return WishlistItem::updateOrCreate(
            ['wishlist_id' => $wishlist->id, 'product_id' => $product->id],
            array_merge(['priority' => $priority->label()], $extra)
        );
    }

    private function privateProduct(User $user, string $name, string $categorySlug, string $description): Product
    {
        $category = Category::where('slug', $categorySlug)->firstOrFail();

        return Product::updateOrCreate(
            ['name' => $name, 'category_id' => $category->id],
            [
                'description' => $description,
                'created_by_user_id' => $user->id,
                'is_public' => false,
                'currency' => 'CLP',
            ]
        );
    }

    private function access(Wishlist $wishlist, User $user, AccessRequestStatus $status, ?string $message = null): WishlistAccess
    {
        return WishlistAccess::updateOrCreate(
            ['wishlist_id' => $wishlist->id, 'user_id' => $user->id],
            [
                'status' => $status->label(),
                'message' => $message,
                'responded_at' => $status->isAwaitingResponse() ? null : now(),
            ]
        );
    }

    private function reservation(WishlistItem $item, User $user, ReservationStatus $status, array $extra = []): Reservation
    {
        return Reservation::updateOrCreate(
            ['wishlist_item_id' => $item->id, 'user_id' => $user->id],
            array_merge([
                'status' => $status->label(),
                // active_flag en 1 mientras bloquea el ítem, NULL cuando no.
                // El índice único se apoya en que los NULL no colisionan.
                'active_flag' => $status->blocksItem() ? 1 : null,
            ], $extra)
        );
    }

    private function report(): void
    {
        $this->command->info('Demo lista:');
        $this->command->line('  usuarios .......... '.User::count());
        $this->command->line('  wishlists ......... '.Wishlist::count());
        $this->command->line('  ítems ............. '.WishlistItem::count());
        $this->command->line('  solicitudes ....... '.WishlistAccess::count());
        $this->command->line('  reservas activas .. '.Reservation::active()->count());
        $this->command->line('  contraseña de todos: password');
    }
}
