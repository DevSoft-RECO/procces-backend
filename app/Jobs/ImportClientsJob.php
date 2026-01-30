<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportClientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $dates;
    protected $jobId;

    /**
     * Create a new job instance.
     *
     * @param string $filePath Absolute path to the file
     * @param array $dates ['desde' => 'YYYYMMDD', 'hasta' => 'YYYYMMDD', 'full' => bool]
     * @param string $jobId Unique identifier for cache tracking
     */
    public function __construct($filePath, $dates, $jobId)
    {
        $this->filePath = $filePath;
        $this->dates = $dates;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Debug Log Start
        file_put_contents(storage_path('logs/job_debug.log'), date('Y-m-d H:i:s') . " INFO: Job Started. ID: {$this->jobId}, Path: {$this->filePath}\n", FILE_APPEND);

        $cacheKey = "import_job_{$this->jobId}";

        try {
            if (!file_exists($this->filePath)) {
                throw new \Exception("El archivo no existe: {$this->filePath}");
            }

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'current_row' => 0,
                'processed' => 0,
                'skipped' => 0,
                'message' => 'Iniciando lectura de archivo...'
            ], 3600); // 1 hora TTL

            $file = fopen($this->filePath, 'r');
            $batchSize = 250;
            $batchData = [];
            $totalProcessed = 0;
            $totalSkipped = 0;
            $currentRow = 0;

            // Count total lines for percentage
            $totalLines = 0;
            $handle = fopen($this->filePath, "r");
            while(!feof($handle)){
                $line = fgets($handle);
                if ($line !== false) $totalLines++;
            }
            fclose($handle);
            $totalLines = max($totalLines - 1, 1); // Subtract header, ensure non-zero

            $desde = $this->dates['desde'] ?? null;
            $hasta = $this->dates['hasta'] ?? ($desde ?: null);
            $full = $this->dates['full'] ?? false;

            while (($row = fgetcsv($file, 0, "|")) !== FALSE) {
                $currentRow++;

                // Skip Header
                if (!is_numeric($row[0])) {
                    continue;
                }

                // Filtering Logic
                if (!$full) {
                    $fechaRow = isset($row[1]) ? trim($row[1]) : null;
                    if (!$fechaRow || $fechaRow < $desde || $fechaRow > $hasta) {
                        $totalSkipped++;

                        if ($currentRow % 1000 == 0) {
                            $this->updateProgress($cacheKey, $totalProcessed, $totalSkipped, "Filtrando registros... (Fila $currentRow)", $totalLines);
                        }
                        continue;
                    }
                }

                // Mapping Data (Extracted from Command logic)
                $data = $this->mapRow($row);
                $batchData[] = $data;

                if (count($batchData) >= $batchSize) {
                    $this->processBatch($batchData);
                    $totalProcessed += count($batchData);
                    $batchData = [];

                    $this->updateProgress($cacheKey, $totalProcessed, $totalSkipped, "Procesando registros... ($totalProcessed insertados)", $totalLines);
                }
            }

            // Insert remaining
            if (count($batchData) > 0) {
                $this->processBatch($batchData);
                $totalProcessed += count($batchData);
            }

            fclose($file);

            // Cleanup: Delete file after successful processing
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }

            // Final Success State
            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'processed' => $totalProcessed,
                'skipped' => $totalSkipped,
                'message' => "Importación completada. $totalProcessed registros actualizados."
            ], 3600);

        } catch (\Throwable $e) {
            $msg = "Import Job Failed: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString();
            Log::error($msg);

            // Fallback logging
            file_put_contents(storage_path('logs/job_debug.log'), date('Y-m-d H:i:s') . " ERROR: $msg\n", FILE_APPEND);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'message' => 'Error durante la importación.'
            ], 3600);

            throw $e;
        }
    }

    private function updateProgress($key, $processed, $skipped, $message, $totalLines)
    {
        $totalProcessed = $processed + $skipped;
        $percentage = ($totalLines > 0) ? round(($totalProcessed / $totalLines) * 100) : 0;

        Cache::put($key, [
            'status' => 'processing',
            'progress' => $percentage,
            'processed' => $processed,
            'skipped' => $skipped,
            'message' => $message
        ], 3600);
    }

    private function processBatch(array $batchData)
    {
        if (empty($batchData)) return;

        $firstItem = $batchData[0];
        $columnsToUpdate = array_keys($firstItem);
        $columnsToUpdate = array_diff($columnsToUpdate, ['codigo_cliente', 'created_at']);

        DB::table('clientes')->upsert(
            $batchData,
            ['codigo_cliente'],
            $columnsToUpdate
        );
    }

    // --- Helpers from Command (Refactored) ---
    private function mapRow($row)
    {
        return [
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
    }

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
        if (strlen($val) === 8 && is_numeric($val)) {
            $year = substr($val, 0, 4);
            $month = substr($val, 4, 2);
            $day = substr($val, 6, 2);
            return "$year-$month-$day";
        }
        return null;
    }
}
