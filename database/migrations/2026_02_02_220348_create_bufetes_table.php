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
        Schema::create('bufetes', function (Blueprint $table) {
            $table->id();

            // Relacion con Usuarios (Tabla local sincronizada)
            $table->unsignedBigInteger('user_id');
            // Nota: No usamos constrained() directo porque a veces el orden de creación importa,
            // pero como users ya existe, podríamos. Sin embargo, para flexibilidad JIT:
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Relacion con Agencias (Tabla local sincronizada)
            $table->unsignedBigInteger('agencia_id');
            $table->foreign('agencia_id')->references('id')->on('agencias')->onDelete('cascade');

            $table->text('descripcion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bufetes');
    }
};
