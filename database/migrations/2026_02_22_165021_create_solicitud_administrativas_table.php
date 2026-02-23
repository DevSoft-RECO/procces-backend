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
        Schema::create('solicitudes_administrativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_expediente')->constrained('nuevos_expedientes')->onDelete('cascade');
            $table->foreignId('id_usuario_solicita')->constrained('users');
            $table->foreignId('id_agencia')->constrained('agencias');
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->enum('estado_solicitud', ['pendiente', 'recibido_por_admin', 'despachado', 'archivado'])->default('pendiente');

            // --- Bloque Despacho ---
            $table->foreignId('id_usuario_despacho')->nullable()->constrained('users');
            $table->timestamp('fecha_despacho')->nullable();
            $table->enum('confirmacion_solicitante', ['pendiente', 'si'])->default('pendiente');

            // --- Bloque Devolución ---
            $table->timestamp('fecha_devolucion_iniciada')->nullable();
            $table->enum('confirmacion_reingreso', ['pendiente', 'si'])->default('pendiente');
            $table->timestamp('fecha_finalizacion')->nullable();

            $table->text('observaciones')->nullable();
            $table->text('observacion_despacho')->nullable();
            $table->string('estado')->default('pendiente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_administrativas');
    }
};
