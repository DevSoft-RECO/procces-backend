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
        Schema::table('seguimiento_fechas', function (Blueprint $table) {
            $table->dateTime('f_aceptado_secretaria_credito')->nullable()->comment('Estado 7')->after('f_almacenado_admin');
            $table->dateTime('f_enviado_abogado')->nullable()->comment('Estado 8')->after('f_aceptado_secretaria_credito');
            $table->dateTime('f_aceptado_abogado')->nullable()->comment('Estado 9')->after('f_enviado_abogado');
            $table->dateTime('f_enviado_secretaria_credito')->nullable()->comment('Estado 10 (Retorno/Envio)')->after('f_aceptado_abogado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento_fechas', function (Blueprint $table) {
            $table->dropColumn([
                'f_aceptado_secretaria_credito',
                'f_enviado_abogado',
                'f_aceptado_abogado',
                'f_enviado_secretaria_credito',
            ]);
        });
    }
};
