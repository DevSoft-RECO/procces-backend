<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportarClientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:clientes
                            {archivo : La ruta del archivo a importar}
                            {--desde= : Fecha inicial YYYYMMDD (Requerido para actualización)}
                            {--hasta= : Fecha final YYYYMMDD (Opcional, defecto igual a desde)}
                            {--full : Forzar carga completa (Peligroso: Ignora fechas)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Carga masiva de clientes con filtro de fechas para actualizaciones incrementales.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('archivo');
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');
        $full = $this->option('full');

        // Validación de seguridad: Se requiere filtro o flag full
        if (!$full && !$desde) {
            $this->error("¡ERROR DE SEGURIDAD!");
            $this->error("Debes especificar una fecha de inicio (--desde=YYYYMMDD) para actualizar.");
            $this->error("Si realmente deseas importar TODO el archivo, usa la opción --full.");
            return 1;
        }

        if (!file_exists($path)) {
            $this->error("El archivo no existe en la ruta: $path");
            return 1;
        }

        // Definir rango de fechas
        if ($full) {
            $this->warn("!!! MODO CARGA COMPLETA ACTIVADO !!! - Se procesarán todos los registros.");
            if (!$this->confirm('¿Estás seguro de continuar?', true)) {
                return 0;
            }
        } else {
            // Si no se define hasta, es igual a desde (un solo día)
            $hasta = $hasta ?: $desde;
            $this->info("Modo Actualización Incremental: Filtrando registros con fecha_actualizacion entre $desde y $hasta");
        }

        $this->info("Iniciando importacion desde: $path");

        $file = fopen($path, 'r');
        $batchSize = 50;
        $batchData = [];
        $totalProcessed = 0;
        $totalSkipped = 0;
        $startTime = microtime(true);

        // Columnas para el upsert (todas menos el PK que se usa para matchear)
        // Se definen dinamicamente o se listan. Dado que son muchas, usaremos updateColumns.

        while (($row = fgetcsv($file, 0, "|")) !== FALSE) {
            // Validar si es cabecera (usualmente el código cliente es numérico)
            if (!is_numeric($row[0])) {
                continue;
            }

            // Lógica de Filtrado por Fecha
            if (!$full) {
                // La fecha de actualización está en el índice 1 (según el mapeo original)
                // Formato en CSV: YYYYMMDD (string)
                $fechaRow = isset($row[1]) ? trim($row[1]) : null;

                if (!$fechaRow) {
                    $totalSkipped++;
                    continue;
                }

                // Comparación de cadenas YYYYMMDD es segura y rápida
                if ($fechaRow < $desde || $fechaRow > $hasta) {
                    $totalSkipped++;
                    continue;
                }
            }

            // Validar que la fila tenga la cantidad esperada de columnas o manejarlo

            // Mapeo de datos
            $data = [
                'codigo_cliente'                => $this->val($row, 0),
                'actualizacion'                 => $this->dateVal($row, 1),
                'nombre1'                       => $this->val($row, 2),
                'nombre2'                       => $this->val($row, 3),
                'nombre3'                       => $this->val($row, 4),
                'apellido1'                     => $this->val($row, 5),
                'apellido2'                     => $this->val($row, 6),
                'apellido_casada'               => $this->val($row, 7),
                'celular'                       => $this->val($row, 8),
                'telefono'                      => $this->val($row, 9),
                'genero'                        => $this->val($row, 10),
                'fecha_apertura'                => $this->dateVal($row, 11),
                'tipo_cliente'                  => $this->val($row, 12),
                'fecha_nacimiento'              => $this->dateVal($row, 13),
                'actividad_economica_ive'       => $this->val($row, 14),
                'alto_riesgo'                   => $this->val($row, 15),
                'id_reple'                      => $this->val($row, 16),
                'dpi'                           => $this->val($row, 17),
                'pasaporte'                     => $this->val($row, 18),
                'licencia'                      => $this->val($row, 19),
                'nit'                           => $this->val($row, 20),
                'departamento'                  => $this->val($row, 21),
                'municipio'                     => $this->val($row, 22),
                'pais'                          => $this->val($row, 23),
                'estado_civil'                  => $this->val($row, 24),
                'depto_nacimiento'              => $this->val($row, 25),
                'muni_nacimiento'               => $this->val($row, 26),
                'pais_nacimiento'               => $this->val($row, 27),
                'nacionalidad'                  => $this->val($row, 28),
                'ocupacion'                     => $this->val($row, 29),
                'profesion'                     => $this->val($row, 30),
                'correo_electronico'            => $this->val($row, 31),
                'actividad_economica'           => $this->val($row, 32),
                'rubro'                         => $this->val($row, 33),
                'sub_rubro'                     => $this->val($row, 34),
                'direccion'                     => $this->val($row, 35),
                'zona_domicilio'                => $this->val($row, 36),
                'pais_domicilio'                => $this->val($row, 37),
                'depto_domicilio'               => $this->val($row, 38),
                'muni_domicilio'                => $this->val($row, 39),
                'relacion_dependencia'          => $this->val($row, 40),
                'nombre_relacion_dependencia'   => $this->val($row, 41),
                'ingresos_laborales'            => $this->decVal($row, 42),
                'moneda_ingreso_laboral'        => $this->val($row, 43),
                'fecha_ingreso_laboral'         => $this->dateVal($row, 44),
                'negocio_propio'                => $this->val($row, 45),
                'nombre_negocio'                => $this->val($row, 46),
                'fecha_inicio_negocio'          => $this->dateVal($row, 47),
                'ingresos_negocio_propio'       => $this->decVal($row, 48),
                'moneda_negocio_propio'         => $this->val($row, 49),
                'ingresos_remesas'              => $this->decVal($row, 50),
                'monto_otros_ingresos'          => $this->decVal($row, 51),
                'otros_ingresos'                => $this->decVal($row, 52),
                'monto_ingresos'                => $this->decVal($row, 53),
                'moneda_ingresos'               => $this->val($row, 54),
                'rango_ingresos'                => $this->val($row, 55),
                'monto_egresos'                 => $this->decVal($row, 56),
                'moneda_egresos'                => $this->val($row, 57),
                'rango_egresos'                 => $this->val($row, 58),
                'act_economica_relacion_dependencia' => $this->val($row, 59),
                'act_economica_negocio'         => $this->val($row, 60),
                'edad'                          => $this->intVal($row, 61),
                'cooperativa'                   => $this->intVal($row, 62),
                'condicion_vivienda'            => $this->val($row, 63),
                'puesto'                        => $this->val($row, 64),
                'direccion_laboral'             => $this->val($row, 65),
                'zona_laboral'                  => $this->val($row, 66),
                'depto_laboral'                 => $this->val($row, 67),
                'muni_laboral'                  => $this->val($row, 68),
                'telefono_laboral'              => $this->val($row, 69),
                'persona_pep'                   => $this->val($row, 70),
                'persona_cpe'                   => $this->val($row, 71),
                'categoria'                     => $this->val($row, 72),
                'descripcion_sub_rubro'         => $this->val($row, 73),
                'descripcion_rubro'             => $this->val($row, 74),
                'coope_creacion'                => $this->intVal($row, 75),
                'parentesco_pep'                => $this->val($row, 76),
                'relacion_pep'                  => $this->val($row, 77),
                'codigo_cli_encargado'          => $this->val($row, 78),
                'act_econ_redep'                => $this->val($row, 79),
                'act_econ_nego'                 => $this->val($row, 80),
                'depto_domi'                    => $this->intVal($row, 81),
                'muni_domi'                     => $this->intVal($row, 82),
                'otr_ing_actprof'               => $this->val($row, 83),
                'otr_ing_actprof_des'           => $this->val($row, 84),
                'otr_ing_manu'                  => $this->val($row, 85),
                'otr_ing_manu_des'              => $this->val($row, 86),
                'otr_ing_rentas'                => $this->val($row, 87),
                'otr_ing_rentas_des'            => $this->val($row, 88),
                'otr_ing_jubila'                => $this->val($row, 89),
                'otr_ing_jubila_des'            => $this->val($row, 90),
                'otr_ing_otrfue'                => $this->val($row, 91),
                'otr_ing_otrfue_des'            => $this->val($row, 92),
                'campo_sector'                  => $this->val($row, 93),
                'dir_neg_propio'                => $this->val($row, 94),
                'zona_neg_propio'               => $this->val($row, 95),
                'pais_neg_propio'               => $this->val($row, 96),
                'depto_neg_propio'              => $this->val($row, 97),
                'ciudad_neg_propio'             => $this->val($row, 98),
                'fecha_expdpi'                  => $this->dateVal($row, 99),
                'fecha_emidpi'                  => $this->dateVal($row, 100),
                'usuario_actualizacion'         => $this->val($row, 101),
                'cooperativa_actualizacion'     => $this->intVal($row, 102),
                'id_representante'              => $this->intVal($row, 103),
                'nombre_representante'          => $this->val($row, 104),
                'relacion_representante'        => $this->val($row, 105),
                'canal_cliente'                 => $this->val($row, 106),
                'created_at'                    => now(),
                'updated_at'                    => now(),
            ];

            $batchData[] = $data;

            if (count($batchData) >= $batchSize) {
                $this->processBatch($batchData);
                $totalProcessed += count($batchData);
                $this->info("Procesados: $totalProcessed registros...");
                $batchData = [];
            }
        }

        // Insertar remanentes
        if (count($batchData) > 0) {
            $this->processBatch($batchData);
            $totalProcessed += count($batchData);
        }

        fclose($file);

        $duration = microtime(true) - $startTime;
        $this->info("¡Importación completada! Total: $totalProcessed registros en " . round($duration, 2) . " segundos.");
        return 0;
    }

    private function processBatch(array $batchData)
    {
        // Upsert permite insertar o actualizar si la llave primaria existe
        // El segundo argumento es la(s) columna(s) para identificar unicidad (PK)
        // El tercer argumento son las columnas que se deben actualizar si ya existe.

        // Obtenemos todas las llaves del primer elemento para saber qué actualizar
        if (empty($batchData)) return;

        $firstItem = $batchData[0];
        $columnsToUpdate = array_keys($firstItem);
        // Quitamos 'codigo_cliente' de la lista de actualización porque es la llave
        // y 'created_at' para no sobreescribir la fecha de creación original si se desea conservar (opcional, aqui actualizamos todo)
        $columnsToUpdate = array_diff($columnsToUpdate, ['codigo_cliente', 'created_at']);

        try {
            DB::table('clientes')->upsert(
                $batchData,
                ['codigo_cliente'],
                $columnsToUpdate
            );
        } catch (\Exception $e) {
            $this->error("Error al insertar lote: " . $e->getMessage());
            throw $e;
        }
    }

    // Helpers
    private function val($row, $index)
    {
        return isset($row[$index]) && trim($row[$index]) !== '' ? trim($row[$index]) : null;
    }

    private function intVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (int)$val : null;
    }

    private function decVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (float)$val : null;
    }

    private function dateVal($row, $index)
    {
        $val = $this->val($row, $index);
        if (!$val) return null;

        // Asumimos formato yyyymmdd (ej: 20251228)
        if (strlen($val) === 8 && is_numeric($val)) {
            $year = substr($val, 0, 4);
            $month = substr($val, 4, 2);
            $day = substr($val, 6, 2);
            return "$year-$month-$day";
        }

        return null;
    }
}
