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
        Schema::create('seguimiento_expedientes', function (Blueprint $table) {
            $table->id('id_seguimiento');

            $table->unsignedBigInteger('id_expediente')->index();
            $table->foreign('id_expediente')
                  ->references('codigo_cliente')
                  ->on('nuevos_expedientes')
                  ->onDelete('cascade');

            $table->integer('id_estado')->comment('1 al 6');

            $table->boolean('enviado_a_archivos')->default(false)->comment('Flag para control administrativo');

            $table->text('observacion_envio')->nullable()->comment('Instrucciones cuando avanza');
            $table->text('observacion_rechazo')->nullable()->comment('Motivo de retorno (Estado 2)');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimiento_expedientes');
    }
};
