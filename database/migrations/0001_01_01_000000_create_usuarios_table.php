<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `usuarios` — modelo de entidades de la guía de seguridad (sección 9).
     *
     * Notas de diseño (ver docs/guia-seguridad.md):
     * - `numero_identificacion` NO se cifra: no es el dato de mayor riesgo y
     *   cifrarlo impediría validar su unicidad directamente.
     * - `ingresos_mensuales` es `decimal`, nunca `float`, para evitar errores
     *   de redondeo en dinero.
     * - Ninguna columna de tarjeta vive aquí: la tabla `tarjetas` está separada
     *   a propósito para aislar el único dato clasificado como Restringido.
     */
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();

            // Identidad (PII — Confidencial)
            $table->string('nombre_completo', 150);
            $table->string('numero_identificacion', 20)->unique();
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->string('telefono', 20);
            $table->string('direccion', 255);

            // Datos financieros (Confidencial)
            $table->string('ocupacion', 100);
            $table->decimal('ingresos_mensuales', 12, 2);
            $table->string('entidad_bancaria', 100);

            // Autorización: solo dos roles, un enum basta (sección 10 de la guía)
            $table->enum('role', ['usuario', 'administrador'])->default('usuario');

            // Plomería estándar de autenticación de Laravel (no son datos de
            // negocio y no contienen PII, por eso no aparecen en la sección 2).
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->timestamps();   // auditoría
            $table->softDeletes();  // el panel admin "elimina" sin borrar físicamente
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
