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
        Schema::create('clientes', function (Blueprint $table) {
            // EMPRESA;CODIGOCLIENTE;NUMERODOCUMENTO;TIPO DOCUENTO;USUARIOASESOR;
            // TASAINTERES;MONTODOCUMENTO;TIPOGARANTIA;fechainicio;CUI;
            // NOMBRE 1;APELLIDO 1;NOMBRE CORTO

            // Primary Key
            $table->integer('codigo_cliente')->primary();

            // Identity
            $table->string('dpi', 20)->nullable()->index(); // CUI
            $table->string('numero_documento', 50)->nullable();

            // Names
            $table->string('nombre1', 150)->nullable();
            $table->string('apellido1', 150)->nullable();
            $table->string('nombre_corto', 150)->nullable();

            // Business/Loan Info
            $table->string('empresa', 100)->nullable();
            $table->string('tipo_documento', 50)->nullable();
            $table->string('usuario_asesor', 50)->nullable();
            $table->string('tipo_garantia', 50)->nullable();

            // Financials
            $table->decimal('tasa_interes', 8, 2)->nullable();
            $table->decimal('monto_documento', 18, 2)->nullable();

            // Dates
            $table->date('fecha_inicio')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
