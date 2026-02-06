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
            $table->enum('es_un_pagare', ['si', 'no'])->nullable()->after('observacion_rechazo');
            $table->string('numero_contrato')->nullable()->after('es_un_pagare');
            $table->string('path_contrato')->nullable()->after('numero_contrato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->dropColumn(['es_un_pagare', 'numero_contrato', 'path_contrato']);
        });
    }
};
