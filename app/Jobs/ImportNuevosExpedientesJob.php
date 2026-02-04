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

class ImportNuevosExpedientesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $jobId;

    public function __construct($filePath, $jobId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
    }

    public function handle()
    {
        $cacheKey = "import_nuevos_job_{$this->jobId}";

        try {
            if (!file_exists($this->filePath)) {
                throw new \Exception("El archivo no existe: {$this->filePath}");
            }

            DB::table('nuevos_expedientes')->truncate();

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Iniciando carga de nuevos expedientes...'
            ], 3600);

            $file = fopen($this->filePath, 'r');
            $batchSize = 250;
            $batchData = [];
            $totalProcessed = 0;
            $currentRow = 0;

            // Mapping Array
            $agencyMap = [
                '2600 CENTRAL' => 99,
                '2600' => 99, // Variant
                '2602 NUEVA CATARINA' => 2,
                '2602' => 2,
                '2603 SAN ANTONIO HUISTA' => 3,
                '2603' => 3,
                '2604 CAMOJA' => 4,
                '2604' => 4,
                '2605 NENTON' => 5,
                '2605' => 5,
                '2606 TODOS SANTOS CUCHUMATÁN' => 6,
                '2606' => 6,
                '2607 HUEHUETENANGO' => 7,
                '2607' => 7,
                '2608 SAN MARCOS HUISTA' => 8,
                '2608' => 8,
                '2609 UNIÓN CANTINIL' => 9,
                '2609' => 9,
                '2610 CONCEPCIÓN HUISTA' => 10,
                '2610' => 10,
                '2611 KAIBIL BALAM' => 11,
                '2611' => 11,
                '2612 LAS CRUCES' => 12,
                '2612' => 12,
                '2613 PETATÁN' => 13,
                '2613' => 13,
                '2614 LA LIBERTAD' => 14,
                '2614' => 14,
                '2615 LA DEMOCRACIA' => 15,
                '2615' => 15,
                '2616' => 16,
                '2616 TAJUMUCO' => 16, // Variant from provided list
                '2616 AG TAJUMUCO' => 16, // Variant from sample
                '2617 SANTA ANA HUISTA' => 17,
                '2617' => 17,
                '2618 TZISBAJ' => 18,
                '2618' => 18
            ];

            while (($row = fgetcsv($file, 0, ";")) !== FALSE) {
                $currentRow++;

                // Skip Header or Empty
                if ($currentRow == 1 && stripos($row[0], 'EMPRESA') !== false) continue;
                if (empty($row[1])) continue;

                try {
                    // Map Agency
                    $rawAgency = trim($row[0]);
                    // Try exact match, then rough match
                    $agencyId = $agencyMap[$rawAgency] ?? null;
                    if (!$agencyId) {
                        // Fallback: Try to match by "26XX" prefix logic if implied,
                        // but sticking to provided list is safer.
                        // Attempt partial match for "todos santos" double space issue
                         foreach ($agencyMap as $key => $val) {
                             if (str_contains($rawAgency, (string)$key)) {
                                 $agencyId = $val;
                                 break;
                             }
                         }
                    }

                    $data = [
                        'id_agencia'       => $agencyId,
                        'codigo_cliente'   => (int) preg_replace('/\D/', '', $row[1]),
                        'numero_documento' => $row[2] ?? null,
                        'tipo_documento'   => $row[3] ?? null,
                        'usuario_asesor'   => $row[4] ?? null,
                        'tasa_interes'     => $this->decVal($row, 5),
                        'monto_documento'  => $this->decVal($row, 6),
                        'tipo_garantia'    => $row[7] ?? null,
                        'fecha_inicio'     => $this->dateVal($row, 8),
                        'cui'              => $row[9] ?? null,
                        // Skip 10 (Nombre1), 11 (Apellido1)
                        'nombre_asociado'  => $row[12] ?? null, // NOMBRE CORTO
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];

                    $batchData[] = $data;

                } catch (\Exception $e) {
                    Log::error("Row $currentRow import error: " . $e->getMessage());
                }

                if (count($batchData) >= $batchSize) {
                    DB::table('nuevos_expedientes')->insert($batchData);
                    $totalProcessed += count($batchData);
                    $batchData = [];
                    $this->updateProgress($cacheKey, $totalProcessed);
                }
            }

            if (!empty($batchData)) {
                DB::table('nuevos_expedientes')->insert($batchData);
                $totalProcessed += count($batchData);
            }

            fclose($file);
            @unlink($this->filePath);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'message' => "Carga completada: $totalProcessed expedientes nuevos."
            ], 3600);

        } catch (\Exception $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'message' => "Error: " . $e->getMessage()
            ], 3600);
            Log::error($e);
            throw $e;
        }
    }

    private function updateProgress($key, $processed) {
        Cache::put($key, [
            'status' => 'processing',
            'progress' => 50, // Indeterminate mostly without line count
            'message' => "Procesados: $processed"
        ], 3600);
    }

    private function decVal($row, $index) {
        $val = $row[$index] ?? null;
        if (!$val) return null;
        return (float) str_replace(',', '', $val);
    }

    private function dateVal($row, $index) {
        $val = $row[$index] ?? null;
        if (!$val) return null;
        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $val)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
