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
        Schema::table('nuevos_expedientes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_lote')->nullable()->index()->after('id');
            $table->foreign('id_lote')->references('id')->on('lotes_importacion')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nuevos_expedientes', function (Blueprint $table) {
            $table->dropForeign(['id_lote']);
            $table->dropColumn('id_lote');
        });
    }
};
