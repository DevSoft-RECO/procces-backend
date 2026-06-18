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
use Illuminate\Support\Facades\Schema;

class ImportExpedientesJob implements ShouldQueue
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

            // INSERTAR REGISTROS (Añadir en lugar de vaciar la tabla)
            // Se comentan las lineas de vaciado para preservar la información actual.
            // Schema::disableForeignKeyConstraints();
            // DB::table('expedientes')->truncate();
            // Schema::enableForeignKeyConstraints();

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'current_row' => 0,
                'processed' => 0,
                'skipped' => 0,
                'message' => 'Iniciando carga de nuevos registros...'
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

            // Detect delimiter
            $delimiter = ';';
            $testHandle = fopen($this->filePath, 'r');
            if ($testHandle) {
                $firstLine = fgets($testHandle);
                fclose($testHandle);
                if ($firstLine !== false) {
                    $semicolons = substr_count($firstLine, ';');
                    $commas = substr_count($firstLine, ',');
                    if ($commas > $semicolons) {
                        $delimiter = ',';
                    }
                }
            }

            // PROCESS CSV
            while (($row = fgetcsv($file, 0, $delimiter)) !== FALSE) {
                $currentRow++;

                // Remove BOM from first column key if present
                $firstCol = strtoupper($row[0] ?? '');
                $firstCol = preg_replace('/^\xEF\xBB\xBF/', '', $firstCol);

                // Skip Header (Check if first column is 'AGENCIA')
                if (str_contains($firstCol, 'AGENCIA')) {
                    continue;
                }

                // Skip empty rows
                if (empty($row[5])) { // CODIGO CLIENTE is at index 5
                     continue;
                }

                // Filtering Logic (Optional, user said delete all and load these, implying full load typically)
                // But keeping logic just in case user uses the date filter frontend controls
                $desde = $this->dates['desde'] ?? null;
                $hasta = $this->dates['hasta'] ?? ($desde ?: null);
                $full = $this->dates['full'] ?? false;

                if (!$full && $desde) {
                     // Date is at index 1 (fecha_inicio)
                     // Format in CSV is dd/mm/yyyy. Need to convert to YYYYMMDD for comparison if using that format strings
                     $rawDate = $row[1] ?? null;
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

        // Utilizamos insert() porque la tabla se trunca o puede recibir nuevos registros sin causar conflicto
        // y la migración ya no requiere que 'codigo_cliente' sea único (ahora tiene clave primaria id autoincremental).
        DB::table('expedientes')->insert($batchData);
    }

    private function mapRow($row)
    {
        // CSV Structure (v2):
        // 0: AGENCIA
        // 1: Fecha Inicio
        // 2: cta_bw
        // 3: numero_documento
        // 4: cif
        // 5: codigo_cliente
        // 6: asociado
        // 7: monto
        // 8: tipo_garantia
        // 9: datos_garantia
        // 10: contrato
        // 11: inscripcion_otros_contratos
        // 12: ingreso
        // 13: inventario
        // 14: salida
        // 15: observacion
        // 16: estado

                return [
            'agencia'              => $this->val($row, 0),
            'fecha_inicio'         => $this->dateVal($row, 1),
            'cta_bw'               => $this->val($row, 2),
            'numero_documento'     => $this->val($row, 3),
            'cif'                  => $this->val($row, 4),
            'codigo_cliente'       => $this->intVal($row, 5),
            'asociado'             => $this->val($row, 6),
            'monto'                => $this->currencyVal($row, 7),
            'tipo_garantia'        => $this->val($row, 8),
            'datos_garantia'       => $this->val($row, 9),
            'contrato'             => $this->val($row, 10),
            'inscripcion_otros_contratos' => $this->val($row, 11),
            'ingreso'              => $this->val($row, 12),
            'inventario'           => $this->val($row, 13),
            'salida'               => $this->val($row, 14),
            'observacion'          => $this->val($row, 15),
            'estado'               => $this->val($row, 16),
            'localizacion'         => $this->val($row, 17),
            'created_at'           => now(),
            'updated_at'           => now(),
        ];

        // return [
        //     'agencia'              => $this->val($row, 0),
        //     'codigo_cliente'       => $this->intVal($row, 1),
        //     'numero_documento'     => $this->val($row, 2),
        //     'tipo_documento'       => $this->val($row, 3),
        //     'usuario_asesor'       => $this->val($row, 4),
        //     'tasa_interes'         => $this->decVal($row, 5),
        //     'monto'                => $this->currencyVal($row, 6),
        //     'tipo_garantia'        => $this->val($row, 7),
        //     'fecha_inicio'         => $this->dateVal($row, 8),
        //     'cui'                  => $this->val($row, 9),
        //     'asociado'             => $this->val($row, 10),
        //     'contrato'             => $this->val($row, 11),
        //     'cta_bw'               => $this->val($row, 12),
        //     'cif'                  => $this->val($row, 13),
        //     'datos_garantia'       => $this->val($row, 14),
        //     'inscripcion_otros_contratos' => $this->val($row, 15),
        //     'ingreso'              => $this->val($row, 16),
        //     'inventario'           => $this->val($row, 17),
        //     'salida'               => $this->val($row, 18),
        //     'observacion'          => $this->val($row, 19),
        //     'estado'               => $this->val($row, 20),
        //     'created_at'           => now(),
        //     'updated_at'           => now(),
        // ];
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

        // Removemos la Q, la Â, comas y cualquier tipo de espacio
        $clean = preg_replace('/[QÂ,\s]/u', '', $val);

        return is_numeric($clean) ? (float)$clean : null;
    }

    // private function currencyVal($row, $index)
    // {
    //     $val = $this->val($row, $index);
    //     if ($val === null) return null;
    //     // Remove 'Q', commas, and spaces
    //     $clean = preg_replace('/[Q,\s]/', '', $val);
    //     return is_numeric($clean) ? (float)$clean : null;
    // }
}
