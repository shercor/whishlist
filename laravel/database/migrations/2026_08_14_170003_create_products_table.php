<?php

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Borrar una categoría no debe llevarse los productos por delante:
            // primero hay que reasignarlos. Por eso restrict y no cascade.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            // Queda en null si el usuario se da de baja: un producto público
            // ya forma parte del catálogo y no debe desaparecer con su autor.
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->decimal('reference_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('CLP');

            // El eje del catálogo: lo que un usuario escribe a mano nace
            // privado y solo lo ve él, así no ensucia la búsqueda de los demás.
            // Un producto se vuelve público cuando se cura para el catálogo.
            $table->boolean('is_public')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_public', 'category_id']);
            $table->index('created_by_user_id');
            // Búsqueda por texto libre sobre el catálogo.
            $table->fullText(['name', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
