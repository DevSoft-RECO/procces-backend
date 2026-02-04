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
        Schema::create('nuevos_expedientes', function (Blueprint $table) {
            // Primary Key from CSV (Codigo Cliente)
            $table->unsignedBigInteger('codigo_cliente')->primary();

            // Mapped ID (was EMPRESA text)
            $table->unsignedInteger('id_agencia')->nullable()->index();

            // Other fields matches CSV structure
            $table->string('numero_documento', 50)->nullable();
            $table->string('tipo_documento', 50)->nullable();
            $table->string('usuario_asesor', 50)->nullable();
            $table->decimal('tasa_interes', 8, 2)->nullable();
            $table->decimal('monto_documento', 18, 2)->nullable();
            $table->string('tipo_garantia', 255)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->string('cui', 20)->nullable()->index();

            // Mapped from NOMBRE CORTO (Col 12)
            // Skipping NOMBRE 1 (Col 10) and APELLIDO 1 (Col 11)
            $table->string('nombre_asociado', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nuevos_expedientes');
    }
};
