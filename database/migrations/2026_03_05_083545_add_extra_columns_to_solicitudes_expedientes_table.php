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
        Schema::table('solicitudes_expedientes', function (Blueprint $table) {
            $table->string('codigo_cliente', 50)->nullable()->after('id_usuario_confirmacion_retorno');
            $table->string('numero_producto', 50)->nullable()->after('codigo_cliente');
            $table->string('observacion_despacho', 300)->nullable()->after('numero_producto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_expedientes', function (Blueprint $table) {
            $table->dropColumn(['codigo_cliente', 'numero_producto', 'observacion_despacho']);
        });
    }
};
