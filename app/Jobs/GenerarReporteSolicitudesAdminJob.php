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

class GenerarReporteSolicitudesAdminJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    protected $reporteId;

    public function __construct($reporteId)
    {
        $this->reporteId = $reporteId;
    }

    public function handle(): void
    {
        Log::info("INICIANDO JOB PRESTAMOS_ADMIN - Reporte ID: {$this->reporteId}");

        $reporte = ReporteExportacion::find($this->reporteId);
        if (!$reporte) {
            Log::error("JOB PRESTAMOS_ADMIN FALLIDO: No se encontró reporte ID {$this->reporteId}");
            return;
        }

        try {
            $reporte->update(['estado' => 'procesando', 'progreso_porcentaje' => 5]);

            $fileName = 'general_solicitudes_admin_' . time() . '_' . uniqid() . '.csv';
            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF8

            $columns = [
                'ID Solicitud', 'No. Documento Asignado', 'CUI Asignado',
                'Usuario Solicitante', 'Agencia del Préstamo', 'Fecha Solicitud',
                'Estado Actual', 'Usuario / Archivo Central (Despacha)', 'Fecha Despachado',
                '¿Solicitante Confirmó Recepción?', 'Fecha de Devolución Iniciada',
                '¿Archivo Confirmó Reingreso?', 'Fecha de Retorno Oficial',
                'Motivo (Observaciones Solicitante)', 'Observaciones del Despacho (Archivo Central)',
                'Fecha Creación Fila'
            ];
            fputcsv($file, $columns);

            $query = DB::table('solicitudes_administrativas')
                ->leftJoin('nuevos_expedientes', 'solicitudes_administrativas.id_expediente', '=', 'nuevos_expedientes.id')
                ->leftJoin('users as u_solicita', 'solicitudes_administrativas.id_usuario_solicita', '=', 'u_solicita.id')
                ->leftJoin('users as u_despacha', 'solicitudes_administrativas.id_usuario_despacho', '=', 'u_despacha.id')
                ->leftJoin('agencias', 'solicitudes_administrativas.id_agencia', '=', 'agencias.id')
                ->select(
                    'solicitudes_administrativas.id',
                    'nuevos_expedientes.numero_documento',
                    'nuevos_expedientes.cui',
                    'u_solicita.name as nombre_solicitante',
                    'agencias.nombre as nombre_agencia',
                    'solicitudes_administrativas.fecha_solicitud',
                    'solicitudes_administrativas.estado_solicitud',
                    'u_despacha.name as nombre_despachador',
                    'solicitudes_administrativas.fecha_despacho',
                    'solicitudes_administrativas.confirmacion_solicitante',
                    'solicitudes_administrativas.fecha_devolucion_iniciada',
                    'solicitudes_administrativas.confirmacion_reingreso',
                    'solicitudes_administrativas.fecha_finalizacion',
                    'solicitudes_administrativas.observaciones as obs_solicitante',
                    'solicitudes_administrativas.observacion_despacho as obs_despacho',
                    'solicitudes_administrativas.created_at'
                )
                ->orderBy('solicitudes_administrativas.id', 'DESC');

            $totalRecords = $query->count();
            $processedRecords = 0;
            $chunkSize = 1000;

            $reporte->update(['progreso_porcentaje' => 10]);

            $query->chunk($chunkSize, function ($solicitudes) use ($file, &$processedRecords, $totalRecords, $reporte) {
                foreach ($solicitudes as $row) {

                    fputcsv($file, [
                        $row->id,
                        $row->numero_documento ?? 'SIN EXPEDIENTE ALINEADO',
                        $row->cui ?? 'SIN CUI',
                        $row->nombre_solicitante ?? 'USUARIO BORRADO',
                        $row->nombre_agencia ?? 'SIN AGENCIA',
                        $row->fecha_solicitud,
                        $row->estado_solicitud,
                        $row->nombre_despachador ?? 'SIN ASIGNAR',
                        $row->fecha_despacho,
                        $row->confirmacion_solicitante == 1 ? 'SÍ' : 'AÚN NO',
                        $row->fecha_devolucion_iniciada,
                        $row->confirmacion_reingreso == 1 ? 'SÍ' : 'AÚN NO',
                        $row->fecha_finalizacion,
                        $row->obs_solicitante,
                        $row->obs_despacho,
                        $row->created_at
                    ]);
                }
                $processedRecords += count($solicitudes);
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
            Log::info("JOB PRESTAMOS_ADMIN FINALIZADO EXITOSAMENTE - Reporte ID: {$this->reporteId}");
        } catch (\Exception $e) {
            if (isset($file) && is_resource($file)) fclose($file);
            Log::error('Fallo creando reporte de Préstamos: ' . $e->getMessage());
            $reporte->update([
                'estado' => 'fallido',
                'error_msg' => substr($e->getMessage(), 0, 200)
            ]);
        }
    }
}
