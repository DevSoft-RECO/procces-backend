<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ReporteExportacion;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerarReporteHistoricoArchivoJob implements ShouldQueue
{
    use Queueable;

    protected $reporteId;

    /**
     * Create a new job instance.
     */
    public function __construct($reporteId)
    {
        $this->reporteId = $reporteId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reporte = ReporteExportacion::find($this->reporteId);

        if (!$reporte) {
            return;
        }

        try {
            $reporte->update(['estado' => 'procesando', 'progreso_porcentaje' => 5]);

            $fileName = 'historico_archivo_' . $reporte->id . '_' . date('Y-m-d_H-i-s') . '.csv';

            // Usaremos un archivo temporal local primero
            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            // Escribir BOM para que Excel lea los acentos UTF-8 correctamente
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados del CSV (Datos Puros de Expediente)
            $columns = [
                'ID', 'Código Cliente', 'Agencia', 'Fecha Inicio', 'CTA BW', 
                'Número Documento', 'CIF', 'Asociado', 'Monto', 'Tipo Garantía', 
                'Datos Garantía', 'Contrato', 'Inscripción Otros Contrados', 
                'Ingreso', 'Inventario', 'Salida', 'Observación', 'Estado', 
                'Localización', 'Fecha Registro'
            ];

            fputcsv($file, $columns);

            $query = DB::table('expedientes')
                ->select(
                    'id', 'codigo_cliente', 'agencia', 'fecha_inicio', 'cta_bw',
                    'numero_documento', 'cif', 'asociado', 'monto', 'tipo_garantia',
                    'datos_garantia', 'contrato', 'inscripcion_otros_contratos',
                    'ingreso', 'inventario', 'salida', 'observacion', 'estado',
                    'localizacion', 'created_at'
                )
                ->orderBy('id', 'DESC');

            $totalRecords = $query->count();
            $processedRecords = 0;
            $chunkSize = 1000;

            if ($totalRecords === 0) {
                $reporte->update(['progreso_porcentaje' => 100]);
            } else {
                $query->chunk($chunkSize, function ($expedientes) use ($file, &$processedRecords, $totalRecords, $reporte) {
                    foreach ($expedientes as $row) {
                        fputcsv($file, [
                            $row->id, 
                            $row->codigo_cliente, 
                            $row->agencia, 
                            $row->fecha_inicio, 
                            $row->cta_bw,
                            $row->numero_documento, 
                            $row->cif, 
                            $row->asociado, 
                            $row->monto, 
                            $row->tipo_garantia,
                            $row->datos_garantia, 
                            $row->contrato, 
                            $row->inscripcion_otros_contratos,
                            $row->ingreso, 
                            $row->inventario, 
                            $row->salida, 
                            $row->observacion, 
                            $row->estado,
                            $row->localizacion, 
                            $row->created_at
                        ]);
                    }
                    $processedRecords += count($expedientes);
                    $porcentaje = min(95, ceil(($processedRecords / $totalRecords) * 100));
                    $reporte->update(['progreso_porcentaje' => $porcentaje]);
                });
            }

            fclose($file);

            // Mover el archivo temporal al Storage oficial configurado en Laravel
            $finalPath = 'reportes/' . $fileName;
            Storage::disk('local')->put($finalPath, file_get_contents($tempPath));

            // Eliminar el temp
            unlink($tempPath);

            $reporte->update([
                'estado' => 'completado',
                'progreso_porcentaje' => 100,
                'file_path' => $finalPath
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando reporte Historico de Archivo: ' . $e->getMessage());
            $reporte->update([
                'estado' => 'fallido',
                'error_msg' => 'Fallo la generación: ' . $e->getMessage()
            ]);
        }
    }
}
