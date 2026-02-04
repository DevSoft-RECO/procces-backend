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
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->nullable();
            $table->dateTime('fecha')->nullable();
            $table->string('propietario', 250)->nullable();
            $table->string('autorizador', 250)->nullable();

            // Datos Registrales
            $table->string('no_finca', 50)->nullable();
            $table->string('folio', 50)->nullable();
            $table->string('libro', 50)->nullable();
            $table->string('no_dominio', 50)->nullable();

            $table->string('referencia', 200)->nullable();
            $table->decimal('monto_poliza', 15, 2)->nullable();
            $table->text('observacion')->nullable();

            // Foreign Keys for catalogs
            // Assumes catalogs tables exist: tipo_documentos, registros_propiedad
            $table->unsignedBigInteger('tipo_documento_id')->nullable()->index();
            $table->foreign('tipo_documento_id')->references('id')->on('tipo_documentos')->nullOnDelete();

            $table->unsignedBigInteger('registro_propiedad_id')->nullable()->index();
            $table->foreign('registro_propiedad_id')->references('id')->on('registro_propiedads')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
