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
            $table->string('recibi_garantia_real')->nullable()->default(null)->after('recibi_pagare');
            $table->string('recibi_contrato')->nullable()->default(null)->after('recibi_garantia_real');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->dropColumn(['recibi_garantia_real', 'recibi_contrato']);
        });
    }
};
