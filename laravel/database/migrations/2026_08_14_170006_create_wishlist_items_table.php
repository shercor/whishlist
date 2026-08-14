<?php

use App\Enums\ItemPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained('wishlists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Cómo lo llama el dueño en su lista, si prefiere otro nombre que
            // el del catálogo. En null se usa products.name.
            $table->string('alias', 200)->nullable();

            // Detalles para quien va a comprar: talla, color, dónde se consigue.
            $table->string('notes', 500)->nullable();

            $table->string('priority', 10)->default(ItemPriority::MEDIUM->label());

            // Ordenamiento manual dentro de la lista.
            $table->unsignedInteger('position')->default(0);

            // El dueño marca acá "ya me llegó" y el ítem deja de ofrecerse a
            // los demás. Es fecha y no booleano para poder decir cuándo pasó.
            $table->timestamp('received_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // A propósito SIN unique(wishlist_id, product_id): repetir el mismo
            // producto es la forma de pedir varias unidades, ya que decidimos
            // que un ítem equivale a una unidad.
            $table->index(['wishlist_id', 'received_at']);
            $table->index(['wishlist_id', 'position']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
