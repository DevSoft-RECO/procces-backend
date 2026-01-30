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
        Schema::create('clientes', function (Blueprint $table) {
            // 1. Codigo Cliente (integer) - Obligatorio (PK)
            $table->integer('codigo_cliente')->primary();

            // 2. Actualizacion (date) - Obligatorio
           $table->date('actualizacion')->nullable()->index();

            // 3. Nombre1 (string) - Puede ser Razon Social
            $table->string('nombre1', 150)->nullable();
            // 4. Nombre2 (string)
            $table->string('nombre2', 50)->nullable();
            // 5. Nombre3 (string)
            $table->string('nombre3', 50)->nullable();
            // 6. Apellido1 (string)
            $table->string('apellido1', 50)->nullable();
            // 7. Apellido2 (string)
            $table->string('apellido2', 50)->nullable();
            // 8. ApellidoCasada (string)
            $table->string('apellido_casada', 50)->nullable();
            // 9. Celular (string)
            $table->string('celular', 20)->nullable();
            // 10. Telefono (string)
            $table->string('telefono', 20)->nullable();
            // 11. Genero (string)
            $table->string('genero', 20)->nullable();
            // 12. Fecha Apertura (date)
            $table->date('fecha_apertura')->nullable();
            // 13. Tipo Cliente (string)
            $table->string('tipo_cliente', 20)->nullable();
            // 14. Fecha Nacimiento (date)
            $table->date('fecha_nacimiento')->nullable();
            // 15. Actividad Economica IVE (string)
            $table->string('actividad_economica_ive', 100)->nullable();
            // 16. Alto Riesgo (string)
            $table->string('alto_riesgo', 5)->nullable();
            // 17. Id Reple (string)
            $table->string('id_reple', 20)->nullable();
            // 18. Dpi (string)
            $table->string('dpi', 20)->nullable()->index();
            // 19. Pasaporte (string)
            $table->string('pasaporte', 20)->nullable();
            // 20. Licencia (string)
            $table->string('licencia', 20)->nullable();
            // 21. Nit (string)
            $table->string('nit', 20)->nullable();
            // 22. Departamento (string)
            $table->string('departamento', 50)->nullable();
            // 23. Municipio (string)
            $table->string('municipio', 50)->nullable();
            // 24. Pais (string)
            $table->string('pais', 50)->nullable();
            // 25. Estado Civil (string)
            $table->string('estado_civil', 20)->nullable();
            // 26. Depto Nacimiento (string)
            $table->string('depto_nacimiento', 50)->nullable();
            // 27. Muni Nacimiento (string)
            $table->string('muni_nacimiento', 50)->nullable();
            // 28. Pais Nacimiento (string)
            $table->string('pais_nacimiento', 50)->nullable();
            // 29. Nacionalidad (string)
            $table->string('nacionalidad', 50)->nullable();
            // 30. Ocupacion (string)
            $table->string('ocupacion', 100)->nullable();
            // 31. Profesion (string)
            $table->string('profesion', 100)->nullable();
            // 32. Correo Electronico (string)
            $table->string('correo_electronico', 100)->nullable();
            // 33. Actividad Economica (string)
            $table->string('actividad_economica', 100)->nullable();
            // 34. Rubro (string)
            $table->string('rubro', 50)->nullable();
            // 35. SubRubro (string)
            $table->string('sub_rubro', 20)->nullable();
            // 36. Direccion (string)
            $table->string('direccion', 200)->nullable();
            // 37. Zona domicilio (string)
            $table->string('zona_domicilio', 10)->nullable();
            // 38. Pais Domicilio (string)
            $table->string('pais_domicilio', 50)->nullable();
            // 39. Depto Domicilio (string)
            $table->string('depto_domicilio', 50)->nullable();
            // 40. Muni Domicilio (string)
            $table->string('muni_domicilio', 50)->nullable();
            // 41. Relacion Dependencia (string)
            $table->string('relacion_dependencia', 15)->nullable();
            // 42. Nombre Relacion Dependencia (string)
            $table->string('nombre_relacion_dependencia', 100)->nullable();
            // 43. Ingresos Laborales (decimal)
            $table->decimal('ingresos_laborales', 18, 5)->nullable();
            // 44. Mondea Ingreso Laboral (string)
            $table->string('moneda_ingreso_laboral', 5)->nullable();
            // 45. Fecha Ingreso Laboral (date)
            $table->date('fecha_ingreso_laboral')->nullable();
            // 46. Negocio Propio (string)
            $table->string('negocio_propio', 5)->nullable();
            // 47. Nombre Negocio (string)
            $table->string('nombre_negocio', 100)->nullable();
            // 48. Fecha Inicio Negocio (date)
            $table->date('fecha_inicio_negocio')->nullable();
            // 49. Ingresos Negocio Propio (decimal)
            $table->decimal('ingresos_negocio_propio', 18, 5)->nullable();
            // 50. Moneda Negocio Propio (string)
            $table->string('moneda_negocio_propio', 5)->nullable();
            // 51. Ingresos Remesas (decimal)
            $table->decimal('ingresos_remesas', 18, 5)->nullable();
            // 52. Monto Otros Ingresos (decimal)
            $table->decimal('monto_otros_ingresos', 18, 5)->nullable();
            // 53. Otros Ingresos (decimal)
            $table->decimal('otros_ingresos', 18, 5)->nullable();
            // 54. Monto Ingresos (decimal)
            $table->decimal('monto_ingresos', 18, 5)->nullable();
            // 55. Moneda Ingresos (string)
            $table->string('moneda_ingresos', 10)->nullable();
            // 56. Rango Ingresos (string)
            $table->string('rango_ingresos', 50)->nullable();
            // 57. Monto Egresos (decimal)
            $table->decimal('monto_egresos', 18, 5)->nullable();
            // 58. Moneda Egresos (string)
            $table->string('moneda_egresos', 10)->nullable();
            // 59. Rango Egresos (string)
            $table->string('rango_egresos', 50)->nullable();
            // 60. Act Economica Relacion Dependencia (string)
            $table->string('act_economica_relacion_dependencia', 100)->nullable();
            // 61. Act Economica Negocio (string)
            $table->string('act_economica_negocio', 100)->nullable();
            // 62. Edad (integer)
            $table->integer('edad')->nullable();
            // 63. Cooperativa (integer)
            $table->integer('cooperativa')->nullable();
            // 64. Condicion Vivienda (string)
            $table->string('condicion_vivienda', 50)->nullable();
            // 65. Puesto (string)
            $table->string('puesto', 80)->nullable();
            // 66. Direccion Laboral (string)
            $table->string('direccion_laboral', 150)->nullable();
            // 67. Zona Laboral (string)
            $table->string('zona_laboral', 10)->nullable();
            // 68. Depto Laboral (string)
            $table->string('depto_laboral', 50)->nullable();
            // 69. Muni Laboral (string)
            $table->string('muni_laboral', 50)->nullable();
            // 70. Telefono Laboral (string)
            $table->string('telefono_laboral', 20)->nullable();
            // 71. Persona PEP (string)
            $table->string('persona_pep', 5)->nullable();
            // 72. Persona CPE (string)
            $table->string('persona_cpe', 5)->nullable();
            // 73. Categoria (string)
            $table->string('categoria', 50)->nullable();
            // 74. Descripcion Sub-Rubro (string)
            $table->string('descripcion_sub_rubro', 100)->nullable();
            // 75. Descripcion Rubro (string)
            $table->string('descripcion_rubro', 100)->nullable();
            // 76. Coope creacion (integer)
            $table->integer('coope_creacion')->nullable();
            // 77. parentesco_pep (string)
            $table->string('parentesco_pep', 50)->nullable();
            // 78. relacion_pep (string)
            $table->string('relacion_pep', 50)->nullable();
            // 79. codigo cli encargado (string)
            $table->string('codigo_cli_encargado', 20)->nullable();
            // 80. Act Econ Redep (string)
            $table->string('act_econ_redep', 100)->nullable();
            // 81. Act Econ Nego (string)
            $table->string('act_econ_nego', 100)->nullable();
            // 82. Depto_Domi (integer)
            $table->integer('depto_domi')->nullable();
            // 83. Muni_Domi (integer)
            $table->integer('muni_domi')->nullable();
            // 84. OTR_ING_ACTPROF (string)
            $table->string('otr_ing_actprof', 10)->nullable();
            // 85. OTR_ING_ACTPROF_DES (string)
            $table->string('otr_ing_actprof_des', 80)->nullable();
            // 86. OTR_ING_MANU (string)
            $table->string('otr_ing_manu', 10)->nullable();
            // 87. OTR_ING_MANU_DES (string)
            $table->string('otr_ing_manu_des', 80)->nullable();
            // 88. OTR_ING_RENTAS (string)
            $table->string('otr_ing_rentas', 10)->nullable();
            // 89. OTR_ING_RENTAS_DES (string)
            $table->string('otr_ing_rentas_des', 80)->nullable();
            // 90. OTR_ING_JUBILA (string)
            $table->string('otr_ing_jubila', 10)->nullable();
            // 91. OTR_ING_JUBILA_DES (string)
            $table->string('otr_ing_jubila_des', 80)->nullable();
            // 92. OTR_ING_OTRFUE (string)
            $table->string('otr_ing_otrfue', 10)->nullable();
            // 93. OTR_ING_OTRFUE_DES (string)
            $table->string('otr_ing_otrfue_des', 80)->nullable();
            // 94. Campo Sector (string)
            $table->string('campo_sector', 70)->nullable();
            // 95. dir_neg_propio (string)
            $table->string('dir_neg_propio', 150)->nullable();
            // 96. zona_neg_propio (string)
            $table->string('zona_neg_propio', 10)->nullable();
            // 97. pais_neg_propio (string)
            $table->string('pais_neg_propio', 50)->nullable();
            // 98. depto_neg_propio (string)
            $table->string('depto_neg_propio', 50)->nullable();
            // 99. ciudad_neg_propio (string)
            $table->string('ciudad_neg_propio', 50)->nullable();
            // 100. fecha_expdpi (date)
            $table->date('fecha_expdpi')->nullable();
            // 101. fecha_emidpi (date)
            $table->date('fecha_emidpi')->nullable();
            // 102. Usuario Actualizacion (string)
            $table->string('usuario_actualizacion', 50)->nullable();
            // 103. Cooperativa Actualizacion (integer)
            $table->integer('cooperativa_actualizacion')->nullable();
            // 104. Id Representante (integer)
            $table->bigInteger('id_representante')->nullable();
            // 105. Nombre Representante (string)
            $table->string('nombre_representante', 100)->nullable();
            // 106. Relacion Representante (string)
            $table->string('relacion_representante', 50)->nullable();
            // 107. Canal Cliente (string)
            $table->string('canal_cliente', 20)->nullable();

            $table->timestamps();

            // Índices adicionales
            // PK (codigo_cliente) ya es index
            // actualizacion ya es index

            // Índice compuesto para validaciones de vigencia
            $table->index(['codigo_cliente', 'actualizacion'], 'idx_cliente_actualizacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
