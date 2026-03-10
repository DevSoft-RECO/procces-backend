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
        Schema::table('confirmaciones_documentos', function (Blueprint $table) {
            // Todos los campos van justo después de user_id
            $table->string('nombre_solicitante')->nullable()->after('user_id');
            $table->string('id_agencia')->nullable()->after('nombre_solicitante');
            $table->string('codigo_cliente')->nullable()->after('id_agencia');
            $table->string('numero_producto')->nullable()->after('codigo_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('confirmaciones_documentos', function (Blueprint $table) {
            $table->dropColumn(['nombre_solicitante', 'id_agencia', 'codigo_cliente', 'numero_producto']);
        });
    }
};
