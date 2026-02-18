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
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->string('tipo_contrato')->nullable()->after('observacion_rechazo');
            $table->string('numero_contrato')->nullable()->after('tipo_contrato');
            $table->string('path_contrato')->nullable()->after('numero_contrato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->dropColumn(['tipo_contrato', 'numero_contrato', 'path_contrato']);
        });
    }
};
