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
        Schema::create('documento_nuevo_expediente', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('nuevo_expediente_id')->index();
            $table->foreign('nuevo_expediente_id')
                  ->references('codigo_cliente')
                  ->on('nuevos_expedientes')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('documento_id')->index();
            $table->foreign('documento_id')
                  ->references('id')
                  ->on('documentos')
                  ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_nuevo_expediente');
    }
};
