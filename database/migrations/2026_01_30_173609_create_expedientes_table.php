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
        Schema::create('expedientes', function (Blueprint $table) {
            // Primary Key
            $table->integer('codigo_cliente')->primary();

            // CSV Mapped Columns
            $table->string('agencia', 100)->nullable();
            $table->string('numero_documento', 50)->nullable();
            $table->string('tipo_documento', 50)->nullable();
            $table->string('usuario_asesor', 50)->nullable();
            $table->decimal('tasa_interes', 8, 2)->nullable();
            $table->decimal('monto', 18, 2)->nullable(); // Was monto_documento
            $table->string('tipo_garantia', 255)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->string('cui', 20)->nullable()->index(); // Was dpi
            $table->string('asociado', 255)->nullable(); // Was nombre_asociado
            $table->string('contrato', 100)->nullable();
            $table->string('cta_bw', 50)->nullable(); // Was cuenta_bw
            $table->string('cif', 50)->nullable();
            $table->text('datos_garantia')->nullable();
            $table->text('inscripcion_otros_contratos')->nullable();

            // Flexible date/text fields
            $table->string('ingreso', 255)->nullable();
            $table->string('inventario', 255)->nullable();
            $table->text('salida')->nullable(); // Can be reasoning text or date

            $table->text('observacion')->nullable();
            $table->string('estado', 50)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
