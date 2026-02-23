<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ReporteExportacion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerarReporteDocumentosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

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
        Log::info("INICIANDO JOB DOCUMENTOS - Reporte ID: {$this->reporteId}");

        $reporte = ReporteExportacion::find($this->reporteId);

        if (!$reporte) {
            Log::error("JOB DOCUMENTOS FALLIDO: No se encontró el reporte ID {$this->reporteId} en la BD");
            return;
        }

        try {
            Log::info("JOB DOCUMENTOS: Reporte Encontrado, actualizando a 'procesando'.");
            $reporte->update(['estado' => 'procesando', 'progreso_porcentaje' => 5]);

            $fileName = 'general_documentos_' . time() . '_' . uniqid() . '.csv';

            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            // BOM para UTF-8 en Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados
            $columns = [
                'ID Documento', 'Número', 'Fecha', 'Propietario', 'Autorizador',
                'Tipo de Documento', 'Registro de Propiedad', 'No. Finca', 'Folio', 'Libro',
                'No. Dominio', 'Referencia', 'Monto Póliza', 'Observación', 'Estado Actual', 'Expedientes Vinculados', 'Fecha de Registro'
            ];
            fputcsv($file, $columns);

            $query = DB::table('documentos')
                ->leftJoin('tipo_documentos', 'documentos.tipo_documento_id', '=', 'tipo_documentos.id')
                ->leftJoin('registro_propiedads', 'documentos.registro_propiedad_id', '=', 'registro_propiedads.id')
                ->select(
                    'documentos.id',
                    'documentos.numero',
                    'documentos.fecha',
                    'documentos.propietario',
                    'documentos.autorizador',
                    'tipo_documentos.nombre as tipo_nombre',
                    'registro_propiedads.nombre as registro_nombre',
                    'documentos.no_finca',
                    'documentos.folio',
                    'documentos.libro',
                    'documentos.no_dominio',
                    'documentos.referencia',
                    'documentos.monto_poliza',
                    'documentos.observacion',
                    'documentos.estado',
                    DB::raw("(SELECT GROUP_CONCAT(ne.numero_documento SEPARATOR ' | ') FROM documento_nuevo_expediente dne JOIN nuevos_expedientes ne ON dne.nuevo_expediente_id = ne.id WHERE dne.documento_id = documentos.id) as expedientes_vinculados"),
                    'documentos.created_at'
                )
                ->orderBy('documentos.id', 'DESC');

            $totalRecords = $query->count();
            $processedRecords = 0;
            $chunkSize = 1000;

            $reporte->update(['progreso_porcentaje' => 10]);

            $query->chunk($chunkSize, function ($documentos) use ($file, &$processedRecords, $totalRecords, $reporte) {

                foreach ($documentos as $row) {
                    fputcsv($file, [
                        $row->id,
                        $row->numero,
                        $row->fecha,
                        $row->propietario,
                        $row->autorizador,
                        $row->tipo_nombre ?? 'SIN TIPO',
                        $row->registro_nombre ?? 'SIN REGISTRO',
                        $row->no_finca,
                        $row->folio,
                        $row->libro,
                        $row->no_dominio,
                        $row->referencia,
                        $row->monto_poliza,
                        $row->observacion,
                        $row->estado,
                        $row->expedientes_vinculados ?? '',
                        $row->created_at
                    ]);
                }

                $processedRecords += count($documentos);
                $percentage = min(99, 10 + round(($processedRecords / $totalRecords) * 89));

                $reporte->update(['progreso_porcentaje' => $percentage]);
            });

            fclose($file);

            $finalPath = 'reportes/' . $fileName;
            Storage::disk('local')->put($finalPath, file_get_contents($tempPath));

            unlink($tempPath);

            $reporte->update([
                'estado' => 'completado',
                'progreso_porcentaje' => 100,
                'file_path' => $finalPath
            ]);

            Log::info("JOB DOCUMENTOS FINALIZADO EXITOSAMENTE - Reporte ID: {$this->reporteId}");

        } catch (\Exception $e) {
            if (isset($file) && is_resource($file)) {
                fclose($file);
            }
            Log::error('Fallo creando reporte de Documentos: ' . $e->getMessage());
            $reporte->update([
                'estado' => 'fallido',
                'error_msg' => 'Error al generar CSV: ' . substr($e->getMessage(), 0, 200)
            ]);
        }
    }
}
