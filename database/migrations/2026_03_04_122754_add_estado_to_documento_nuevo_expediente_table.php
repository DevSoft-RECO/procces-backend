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
        Schema::table('documento_nuevo_expediente', function (Blueprint $table) {
            $table->enum('estado', ['activo', 'cancelado'])->default('activo')->after('documento_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_nuevo_expediente', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
