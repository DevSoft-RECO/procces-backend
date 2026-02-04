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
        Schema::create('seguimiento_expedientes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('nuevo_expediente_id')->index();
            $table->foreign('nuevo_expediente_id')
                  ->references('codigo_cliente')
                  ->on('nuevos_expedientes')
                  ->onDelete('cascade');

            // Assuming users table exists and id is unsignedBigInteger
            // If authentication uses a different logic (like username), adapt here.
            // Using a generic 'usuario_id' or 'usuario' string if user table structure is unsure
            // Based on previous files, 'usuario_asesor' is a string.
             //$table->unsignedBigInteger('usuario_id')->nullable();
             $table->string('usuario', 100)->nullable(); // Recording username for simplicity based on current auth style

            $table->string('paso', 100)->comment('Etapa del flujo: Ingreso, Revision, etc');
            $table->string('estado', 50)->comment('Estado en esa etapa: Pendiente, Aprobado, Rechazado');
            $table->text('observacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguimiento_expedientes');
    }
};
