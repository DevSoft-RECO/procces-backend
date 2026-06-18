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
    protected $dates;
    protected $fileName;
    protected $userId;

    public function __construct($filePath, $jobId, $dates = [], $fileName = null, $userId = null)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
        $this->dates = $dates;
        $this->fileName = $fileName;
        $this->userId = $userId;
    }


    public function handle()
    {
        $cacheKey = "import_nuevos_job_{$this->jobId}";

        try {
            if (!file_exists($this->filePath)) {
                throw new \Exception("El archivo no existe: {$this->filePath}");
            }

            // DB::table('nuevos_expedientes')->truncate(); // REMOVED

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Iniciando carga de nuevos expedientes...'
            ], 3600);

            $lote = \App\Models\LoteImportacion::create([
                'nombre_archivo' => $this->fileName,
                'usuario_id' => $this->userId,
                'registros_totales' => 0,
            ]);

            $file = fopen($this->filePath, 'r');
            $batchSize = 250;
            $batchData = [];
            $totalProcessed = 0;
            $currentRow = 0;
            $totalSkipped = 0;

            // Mapping Array
            $agencyMap = [
                '2600 CENTRAL' => 1,
                '2600' => 1,
                '2602 NUEVA CATARINA' => 2,
                '2602' => 2,
                '2603 SAN ANTONIO HUISTA' => 3,
                '2603' => 3,
                '2604 CAMOJA' => 4,
                '2604' => 4,
                '2605 NENTON' => 5,
                '2605' => 5,
                '2606 TODOS SANTOS CUCHUMATAN' => 6,
                '2606' => 6,
                '2607 HUEHUETENANGO' => 7,
                '2607' => 7,
                '2608 SAN MARCOS HUISTA' => 8,
                '2608' => 8,
                '2609 UNION CANTINIL' => 9,
                '2609' => 9,
                '2610 CONCEPCION HUISTA' => 10,
                '2610' => 10,
                '2611 KAIBIL BALAM' => 11,
                '2611' => 11,
                '2612 LAS CRUCES' => 12,
                '2612' => 12,
                '2613 PETATAN' => 13,
                '2613' => 13,
                '2614 LA LIBERTAD' => 14,
                '2614' => 14,
                '2615 LA DEMOCRACIA' => 15,
                '2615' => 15,
                '2616' => 16,
                '2616 TAJUMUCO' => 16,
                '2616 AG TAJUMUCO' => 16,
                '2617 SANTA ANA HUISTA' => 17,
                '2617' => 17,
                '2618 TZISBAJ' => 18,
                '2618' => 18
            ];

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

            while (($row = fgetcsv($file, 0, $delimiter)) !== FALSE) {
                $currentRow++;

                // Skip Header or Empty
                if ($currentRow == 1 && stripos($row[0], 'EMPRESA') !== false) continue;
                if (empty($row[1])) continue;

                // Date Filter Logic
                $desde = $this->dates['desde'] ?? null;
                $hasta = $this->dates['hasta'] ?? ($desde ?: null);

                if ($desde) {
                    $rawDate = $row[7] ?? null; // FECHA INICIO shifted to index 7 (was 8)
                    if ($rawDate) {
                        try {
                            $rowDate = \Carbon\Carbon::createFromFormat('d/m/Y', $rawDate)->format('Ymd');
                            if ($rowDate < $desde || ($hasta && $rowDate > $hasta)) {
                                $totalSkipped++;
                                continue;
                            }
                        } catch (\Exception $e) {
                            $totalSkipped++;
                            continue;
                        }
                    }
                }

                try {
                    // Map Agency
                    $rawAgency = trim($row[0]);
                    $agencyId = $agencyMap[$rawAgency] ?? null;
                    if (!$agencyId) {
                         foreach ($agencyMap as $key => $val) {
                             if (str_contains($rawAgency, (string)$key)) {
                                 $agencyId = $val;
                                 break;
                             }
                         }
                    }

                    $data = [
                        'id_lote'          => $lote->id,
                        'id_agencia'       => $agencyId,
                        'codigo_cliente'   => (int) preg_replace('/\D/', '', $row[1]),
                        'numero_documento' => $row[2] ?? null,
                        // 'tipo_documento' removed (index 3 gone)
                        'usuario_asesor'   => $row[3] ?? null, // Shifted from 4
                        'tasa_interes'     => $this->decVal($row, 4), // Shifted from 5
                        'monto_documento'  => $this->decVal($row, 5), // Shifted from 6
                        'tipo_garantia'    => $row[6] ?? null, // Shifted from 7
                        'fecha_inicio'     => $this->dateVal($row, 7), // Shifted from 8
                        'cui'              => $row[8] ?? null, // Shifted from 9
                        'nombre_asociado'  => $row[9] ?? null, // Shifted from 12? Assuming 10,11 skipped -> 9,10 skipped -> 11 used?
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

            // Update lote totals
            $lote->update(['registros_totales' => $totalProcessed]);


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
