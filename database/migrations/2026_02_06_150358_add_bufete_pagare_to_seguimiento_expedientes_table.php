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
            $table->unsignedBigInteger('bufete_id')->nullable()->after('archivo_administrativo');
            $table->string('recibi_pagare')->nullable()->default(null)->after('bufete_id'); // 'Si', 'No' or null

            // Foreign key to bufetes table
            $table->foreign('bufete_id')->references('id')->on('bufetes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->dropForeign(['bufete_id']);
            $table->dropColumn(['bufete_id', 'recibi_pagare']);
        });
    }
};
