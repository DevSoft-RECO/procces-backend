<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Importante para el índice FullText

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $table) {
            // ID Autoincremental como llave primaria única
            $table->id();

            // Código de cliente (ahora permite duplicados históricos)
            $table->integer('codigo_cliente')->index();

            $table->string('agencia', 100)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->string('cta_bw', 50)->nullable();

            // INDEXACIÓN: Número de documento para búsquedas rápidas
            $table->string('numero_documento', 50)->nullable()->index();

            $table->string('cif', 50)->nullable();
            $table->string('asociado', 255)->nullable();
            $table->decimal('monto', 18, 2)->nullable();
            $table->string('tipo_garantia', 255)->nullable();

            // Campo de texto para datos de garantía
            $table->text('datos_garantia')->nullable();

            $table->string('contrato', 100)->nullable();
            $table->text('inscripcion_otros_contratos')->nullable();
            $table->string('ingreso', 255)->nullable();
            $table->string('inventario', 255)->nullable();
            $table->text('salida')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 50)->nullable();

            $table->timestamps();
        });

        // INDEXACIÓN FULLTEXT para el campo TEXT (Específico para búsquedas de palabras)
        // Esto permite usar: WHERE MATCH(datos_garantia) AGAINST('termino')
        DB::statement('ALTER TABLE expedientes ADD FULLTEXT fulltext_garantia(datos_garantia)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
