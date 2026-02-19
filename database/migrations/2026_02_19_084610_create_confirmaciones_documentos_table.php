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
        Schema::create('confirmaciones_documentos', function (Blueprint $table) {
            $table->id();

            // Relación opcional con documento existente.
            // Si es un documento "inventado" o no existente, será null.
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();

            // Campos del documento (duplicados para el registro histórico de lo que se validó)
            $table->string('numero');
            $table->date('fecha');

            $table->string('propietario')->nullable();
            $table->string('autorizador')->nullable();
            $table->string('no_finca')->nullable();
            $table->string('folio')->nullable();
            $table->string('libro')->nullable();
            $table->string('no_dominio')->nullable();
            $table->string('referencia')->nullable();
            $table->decimal('monto_poliza', 15, 2)->nullable();
            $table->text('observacion')->nullable();

            // Guardamos el NOMBRE del tipo y registro, no el ID, según requerimiento.
            $table->string('tipo_documento')->nullable();
            $table->string('registro_propiedad')->nullable();

            // Campos de la confirmación
            $table->dateTime('fecha_consulta')->nullable();
            $table->enum('confirmacion', ['SI', 'NO'])->nullable();
            $table->text('observacion_confirmacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmaciones_documentos');
    }
};
