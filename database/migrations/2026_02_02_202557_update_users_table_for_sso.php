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
        // Eliminamos la tabla existente para recrearla con la estructura JIT
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions'); // A veces sessions depende de users

        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // ID manual (Esclavo de Madre)
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
