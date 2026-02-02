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
        Schema::create('agencias', function (Blueprint $table) {
            // ID Espejo de App Madre (Manual Primary Key)
            $table->unsignedBigInteger('id')->primary();

            // Datos de Madre
            $table->string('nombre');
            $table->integer('codigo')->unique(); // Codigo de Madre (ej. 101, 102)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencias');
    }
};
