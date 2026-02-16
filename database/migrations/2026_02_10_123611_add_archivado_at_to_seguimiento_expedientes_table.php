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
            $table->timestamp('archivado_at')->nullable()->after('recibi_contrato');
            $table->text('observacion_legal')->nullable()->after('archivado_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seguimiento_expedientes', function (Blueprint $table) {
            $table->dropColumn(['observacion_legal', 'archivado_at']);
        });
    }
};
