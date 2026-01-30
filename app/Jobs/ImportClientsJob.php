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

            // TRUNCATE TABLE AS REQUESTED
            DB::table('clientes')->truncate();

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'current_row' => 0,
                'processed' => 0,
                'skipped' => 0,
                'message' => 'Limpiando base de datos e iniciando carga...'
            ], 3600);

            $file = fopen($this->filePath, 'r');
            $batchSize = 250;
            $batchData = [];
            $totalProcessed = 0;
            $totalSkipped = 0;
            $currentRow = 0;

            // Count total lines
            $totalLines = 0;
            $handle = fopen($this->filePath, "r");
            while(!feof($handle)){
                $line = fgets($handle);
                if ($line !== false) $totalLines++;
            }
            fclose($handle);
            $totalLines = max($totalLines - 1, 1);

            // PROCESS CSV
            while (($row = fgetcsv($file, 0, ";")) !== FALSE) { // Changed delimiter to ';'
                $currentRow++;

                // Skip Header (Check if first column is 'EMPRESA')
                if (str_contains(strtoupper($row[0] ?? ''), 'EMPRESA')) {
                    continue;
                }

                // Skip empty rows
                if (empty($row[1])) { // CODIGO CLIENTE is at index 1
                     continue;
                }

                // Filtering Logic (Optional, user said delete all and load these, implying full load typically)
                // But keeping logic just in case user uses the date filter frontend controls
                $desde = $this->dates['desde'] ?? null;
                $hasta = $this->dates['hasta'] ?? ($desde ?: null);
                $full = $this->dates['full'] ?? false;

                if (!$full && $desde) {
                     // Date is at index 8 (fechainicio)
                     // Format in CSV is dd/mm/yyyy. Need to convert to YYYYMMDD for comparison if using that format strings
                     $rawDate = $row[8] ?? null;
                     $compDate = $this->parseDateForComparison($rawDate);

                     if (!$compDate || $compDate < $desde || $compDate > $hasta) {
                        $totalSkipped++;
                         if ($currentRow % 1000 == 0) {
                            $this->updateProgress($cacheKey, $totalProcessed, $totalSkipped, "Filtrando... (Fila $currentRow)", $totalLines);
                        }
                        continue;
                     }
                }

                $data = $this->mapRow($row);
                $batchData[] = $data;

                if (count($batchData) >= $batchSize) {
                    $this->processBatch($batchData);
                    $totalProcessed += count($batchData);
                    $batchData = [];

                    $this->updateProgress($cacheKey, $totalProcessed, $totalSkipped, "Procesando... ($totalProcessed insertados)", $totalLines);
                }
            }

            // Insert remaining
            if (count($batchData) > 0) {
                $this->processBatch($batchData);
                $totalProcessed += count($batchData);
            }

            fclose($file);

            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'processed' => $totalProcessed,
                'skipped' => $totalSkipped,
                'message' => "Importación completada. $totalProcessed registros cargados."
            ], 3600);

        } catch (\Throwable $e) {
            $msg = "Import Job Failed: " . $e->getMessage();
            Log::error($msg);

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

        // Using insert because we truncated, so no need for upsert overhead
        // But upsert is safer if duplicates exist in CSV
        DB::table('clientes')->upsert(
            $batchData,
            ['codigo_cliente'],
            [
               'empresa', 'numero_documento', 'tipo_documento', 'usuario_asesor',
               'tasa_interes', 'monto_documento', 'tipo_garantia', 'fecha_inicio',
               'dpi', 'nombre1', 'apellido1', 'nombre_corto', 'updated_at'
            ]
        );
    }

    private function mapRow($row)
    {
        // CSV Structure:
        // 0: EMPRESA
        // 1: CODIGOCLIENTE
        // 2: NUMERODOCUMENTO
        // 3: TIPO DOCUENTO
        // 4: USUARIOASESOR
        // 5: TASAINTERES
        // 6: MONTODOCUMENTO
        // 7: TIPOGARANTIA
        // 8: fechainicio (dd/mm/yyyy)
        // 9: CUI (dpi)
        // 10: NOMBRE 1
        // 11: APELLIDO 1
        // 12: NOMBRE CORTO

        return [
            'empresa'           => $this->val($row, 0),
            'codigo_cliente'    => $this->intVal($row, 1),
            'numero_documento'  => $this->val($row, 2),
            'tipo_documento'    => $this->val($row, 3),
            'usuario_asesor'    => $this->val($row, 4),
            'tasa_interes'      => $this->decVal($row, 5),
            'monto_documento'   => $this->decVal($row, 6),
            'tipo_garantia'     => $this->val($row, 7),
            'fecha_inicio'      => $this->dateVal($row, 8),
            'dpi'               => $this->val($row, 9),
            'nombre1'           => $this->val($row, 10),
            'apellido1'         => $this->val($row, 11),
            'nombre_corto'      => $this->val($row, 12),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }

    private function val($row, $index)
    {
        return isset($row[$index]) && trim($row[$index]) !== '' ? trim($row[$index]) : null;
    }

    private function intVal($row, $index)
    {
        $val = $this->val($row, $index);
        // Remove non-numeric characters just in case
        return $val !== null ? (int)preg_replace('/\D/', '', $val) : null;
    }

    private function decVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (float)str_replace(',', '', $val) : null; // Handle potential commas
    }

    private function dateVal($row, $index)
    {
        // Input: 28/01/2026 (dd/mm/yyyy)
        // Output: 2026-01-28 (yyyy-mm-dd)
        $val = $this->val($row, $index);
        if (!$val) return null;

        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $val)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateForComparison($val) {
         if (!$val) return null;
         try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $val)->format('Ymd');
        } catch (\Exception $e) {
            return null;
        }
    }
}
