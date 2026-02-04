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
        Schema::create('detalle_garantia', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            // Link to 'nuevos_expedientes' via 'codigo_cliente'
            $table->unsignedBigInteger('nuevo_expediente_id')->index();
            $table->foreign('nuevo_expediente_id')
                  ->references('codigo_cliente')
                  ->on('nuevos_expedientes')
                  ->onDelete('cascade');

            // Link to 'garantias' via 'id'
            $table->unsignedBigInteger('garantia_id')->index();
            $table->foreign('garantia_id')
                  ->references('id')
                  ->on('garantias')
                  ->onDelete('cascade');

            // Additional Fields
            $table->string('codeudor1', 200)->nullable();
            $table->string('codeudor2', 200)->nullable();
            $table->string('codeudor3', 200)->nullable();
            $table->string('codeudor4', 200)->nullable();

            $table->string('observacion1', 200)->nullable();
            $table->string('observacion2', 200)->nullable();
            $table->string('observacion3', 200)->nullable();
            $table->string('observacion4', 200)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_garantia');
    }
};
