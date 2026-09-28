<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `tarjetas` — separada de `usuarios` a propósito (guía, sección 9):
     * aísla el único dato clasificado como **Restringido** en un modelo pequeño
     * y auditable, reduce lo que se expone en cualquier consulta sobre `usuarios`
     * y concentra toda la lógica de cifrado en un solo lugar del código.
     */
    public function up(): void
    {
        Schema::create('tarjetas', function (Blueprint $table) {
            $table->id();

            // `unique` porque el formulario solo pide una tarjeta por perfil (1 a 1).
            $table->foreignId('usuario_id')->unique()->constrained('usuarios')->cascadeOnDelete();

            // Único campo cifrado a nivel de aplicación (cast `encrypted`).
            // Es `text` porque el criptograma de Laravel es más largo que el
            // número original y su longitud varía.
            $table->text('numero_tarjeta');

            // En claro a propósito: permite pintar `**** **** **** 1234` sin
            // descifrar en cada render. Cuatro dígitos aislados no son
            // explotables sin el resto del número.
            $table->string('ultimos_4_digitos', 4);

            $table->string('nombre_titular', 150);   // puede diferir del dueño de la cuenta
            $table->string('fecha_vencimiento', 5);  // MM/YY

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarjetas');
    }
};
