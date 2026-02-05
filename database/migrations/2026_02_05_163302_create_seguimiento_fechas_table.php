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
        Schema::create('seguimiento_fechas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_expediente')->primary();
            $table->foreign('id_expediente')
                  ->references('codigo_cliente')
                  ->on('nuevos_expedientes')
                  ->onDelete('cascade');

            $table->dateTime('f_enviado_secretaria')->nullable()->comment('Estado 1');
            $table->dateTime('f_retorno_asesores')->nullable()->comment('Estado 2 (Se sobrescribe)');
            $table->dateTime('f_aceptado_secretaria')->nullable()->comment('Estado 3');
            $table->dateTime('f_enviado_archivos')->nullable()->comment('Estado 4');
            $table->dateTime('f_enviado_protocolos')->nullable()->comment('Estado 5');
            $table->dateTime('f_almacenado_admin')->nullable()->comment('Estado 6');
            $table->dateTime('f_ultimo_rechazo')->nullable()->comment('Marca de tiempo extra para auditoría');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimiento_fechas');
    }
};
