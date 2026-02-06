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
            $table->integer('id_estado_secundario')->nullable()->after('id_estado')->comment('Sub-estado para flujos paralelos');
        });

        // Modificar enviado_a_archivos de boolean a ENUM('Si', 'No') preservando datos
        // 1. Renombrar
        DB::statement("ALTER TABLE seguimiento_expedientes CHANGE enviado_a_archivos enviado_old TINYINT(1) DEFAULT 0");
        // 2. Crear nueva
        DB::statement("ALTER TABLE seguimiento_expedientes ADD enviado_a_archivos ENUM('Si', 'No') DEFAULT 'No' AFTER id_estado_secundario");
        // 3. Migrar datos
        DB::update("UPDATE seguimiento_expedientes SET enviado_a_archivos = CASE WHEN enviado_old = 1 THEN 'Si' ELSE 'No' END");
        // 4. Borrar vieja
        DB::statement("ALTER TABLE seguimiento_expedientes DROP COLUMN enviado_old");
    }

    public function down(): void
    {
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->dropColumn('id_estado_secundario');
        });

        // Revertir enviado_a_archivos a boolean
        DB::statement("ALTER TABLE seguimiento_expedientes ADD enviado_bool TINYINT(1) DEFAULT 0");
        DB::update("UPDATE seguimiento_expedientes SET enviado_bool = CASE WHEN enviado_a_archivos = 'Si' THEN 1 ELSE 0 END");
        DB::statement("ALTER TABLE seguimiento_expedientes DROP COLUMN enviado_a_archivos");
        DB::statement("ALTER TABLE seguimiento_expedientes CHANGE enviado_bool enviado_a_archivos TINYINT(1) DEFAULT 0");
    }
};
