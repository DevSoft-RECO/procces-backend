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
            DB::table('expedientes')->truncate();

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

                // Remove BOM from first column key if present
                $firstCol = strtoupper($row[0] ?? '');
                $firstCol = preg_replace('/^\xEF\xBB\xBF/', '', $firstCol);

                // Skip Header (Check if first column is 'AGENCIA')
                if (str_contains($firstCol, 'AGENCIA')) {
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

                try {
                    $data = $this->mapRow($row);
                    if (!$data['codigo_cliente']) {
                         $totalSkipped++;
                         Log::warning("Skipped row $currentRow: Missing codigo_cliente", $row);
                         continue;
                    }
                    $batchData[] = $data;
                } catch (\Exception $e) {
                    $totalSkipped++;
                    Log::error("Error mapping row $currentRow: " . $e->getMessage(), $row);
                    continue;
                }

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
        DB::table('expedientes')->upsert(
            $batchData,
            ['codigo_cliente'],
            [
               'agencia', 'numero_documento', 'tipo_documento', 'usuario_asesor',
               'tasa_interes', 'monto', 'tipo_garantia', 'fecha_inicio',
               'cui', 'asociado', 'contrato', 'cta_bw', 'cif',
               'datos_garantia', 'inscripcion_otros_contratos', 'ingreso', 'inventario',
               'salida', 'observacion', 'estado', 'updated_at'
            ]
        );
    }

    private function mapRow($row)
    {
        // CSV Structure (v2):
        // 0: AGENCIA
        // 1: CodigoCliente
        // 2: Numero Docuemnto
        // 3: Tipo Documento
        // 4: UsusuarioAsesor
        // 5: Tasa Interes
        // 6: MONTO (Q and ,)
        // 7: TIPO DE GARANTIA
        // 8: FECHA INICIO (d/m/Y)
        // 9: CUI
        // 10: ASOCIADO
        // 11: CONTRATO
        // 12: CTA.BW
        // 13: CIF
        // 14: DATOS DE GARANTIA
        // 15: INSCRIPCION/OTROS CONTRATOS
        // 16: INGRESO
        // 17: INVENTARIO
        // 18: SALIDA
        // 19: OBSERVACIÓN
        // 20: ESTADO

        return [
            'agencia'              => $this->val($row, 0),
            'codigo_cliente'       => $this->intVal($row, 1),
            'numero_documento'     => $this->val($row, 2),
            'tipo_documento'       => $this->val($row, 3),
            'usuario_asesor'       => $this->val($row, 4),
            'tasa_interes'         => $this->decVal($row, 5),
            'monto'                => $this->currencyVal($row, 6),
            'tipo_garantia'        => $this->val($row, 7),
            'fecha_inicio'         => $this->dateVal($row, 8),
            'cui'                  => $this->val($row, 9),
            'asociado'             => $this->val($row, 10),
            'contrato'             => $this->val($row, 11),
            'cta_bw'               => $this->val($row, 12),
            'cif'                  => $this->val($row, 13),
            'datos_garantia'       => $this->val($row, 14),
            'inscripcion_otros_contratos' => $this->val($row, 15),
            'ingreso'              => $this->val($row, 16),
            'inventario'           => $this->val($row, 17),
            'salida'               => $this->val($row, 18),
            'observacion'          => $this->val($row, 19),
            'estado'               => $this->val($row, 20),
            'created_at'           => now(),
            'updated_at'           => now(),
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
    private function currencyVal($row, $index)
    {
        $val = $this->val($row, $index);
        if ($val === null) return null;
        // Remove 'Q', commas, and spaces
        $clean = preg_replace('/[Q,\s]/', '', $val);
        return is_numeric($clean) ? (float)$clean : null;
    }
}
