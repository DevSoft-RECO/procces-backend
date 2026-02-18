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
        Schema::create('solicitudes_expedientes', function (Blueprint $table) {
            $table->id();

            // Relación opcional con expediente existente
            $table->unsignedBigInteger('id_expediente')->nullable()->index();
            $table->foreign('id_expediente')->references('id')->on('nuevos_expedientes')->nullOnDelete();

            // Datos del documento (Redundancia o Manual)
            $table->string('numero_documento', 50)->nullable();
            $table->string('titulo_nombre', 255)->nullable();


            // Solicitud
            $table->unsignedBigInteger('id_agencia')->index();
            $table->foreign('id_agencia')->references('id')->on('agencias');

            $table->unsignedBigInteger('id_usuario_solicitante')->index();
            $table->foreign('id_usuario_solicitante')->references('id')->on('users');

            $table->enum('tipo_retiro', ['Temporal', 'Definitivo']);
            $table->text('justificacion');
            $table->dateTime('fecha_solicitud');

            // Despacho
            $table->unsignedBigInteger('id_usuario_despacho')->nullable()->index();
            $table->foreign('id_usuario_despacho')->references('id')->on('users');

            $table->dateTime('fecha_envio')->nullable();

            // Campos para entrega
            $table->unsignedBigInteger('id_usuario_entrega')->nullable()->index()->comment('Usuario que entrega la garantía');
            $table->foreign('id_usuario_entrega')->references('id')->on('users');

            $table->unsignedBigInteger('id_agencia_entrega')->nullable()->index()->comment('Agencia donde se entrega la garantía');
            $table->foreign('id_agencia_entrega')->references('id')->on('agencias');

            $table->string('evidencia_entrega_path', 2048)->nullable()->comment('Ruta del archivo de evidencia de entrega');

            // Estado: 0=Archivado(Default/En Bodega), 1=Solicitado, 2=Enviado Temporal, 3=Enviado Definitivo
            $table->integer('estado_actual')->default(1)->comment('0=Archivado, 1=Solicitado, 2=Enviado Temporal, 3=Enviado Definitivo');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_expedientes');
    }
};
