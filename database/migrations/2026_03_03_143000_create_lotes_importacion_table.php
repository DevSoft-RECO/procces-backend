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
        Schema::create('lotes_importacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->integer('registros_totales')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes_importacion');
    }
};
