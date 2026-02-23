<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ReporteExportacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerarReporteSeguimientoCsvJob implements ShouldQueue
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

            $fileName = 'seguimiento_expedientes_' . $reporte->id . '_' . date('Y-m-d_H-i-s') . '.csv';

            // Usaremos un archivo temporal local primero
            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            // Escribir BOM para que Excel lea los acentos UTF-8 correctamente
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados del CSV
            $columns = [
                'Expediente ID', 'Código Cliente', 'CUI', 'Nombre Asociado', 'Agencia ID',
                'Número Documento', 'Usuario Asesor', 'Tasa Interés', 'Monto Documento',
                'Tipo Garantía ID', 'Fecha Inicio', 'Estado Actual', 'Estado Secundario',
                'Bufete Asignado', 'Recibí Garantía Real', 'Recibí Contrato',
                'Tipo Contrato', 'Número Contrato', 'Contrato escaneado', 'Observación Legal',
                'Fec. Enviado Secretaría', 'Fec. Retorno Asesores', 'Fec. Aceptado Secretaría',
                'Fec. Enviado Archivos', 'Fec. Enviado Protocolos', 'Fec. Almacenado Admin',
                'Fec. Aceptado Sec. Crédito', 'Fec. Enviado Abogado', 'Fec. Aceptado Abogado',
                'Fec. Enviado Secret. Crédito', 'Archivado At (Finalizado)'
            ];

            fputcsv($file, $columns);

            $query = DB::table('nuevos_expedientes')
                ->leftJoin('seguimiento_expedientes as se', 'nuevos_expedientes.id', '=', 'se.id_expediente')
                ->leftJoin('seguimiento_fechas as sf', 'nuevos_expedientes.id', '=', 'sf.id_expediente')
                ->leftJoin('tipo_estados as te1', 'se.id_estado', '=', 'te1.id')
                ->leftJoin('tipo_estados as te2', 'se.id_estado_secundario', '=', 'te2.id')
                ->leftJoin('bufetes', 'se.bufete_id', '=', 'bufetes.id')
                ->leftJoin('users as u_bufete', 'bufetes.user_id', '=', 'u_bufete.id')
                ->select(
                    'nuevos_expedientes.id', 'nuevos_expedientes.codigo_cliente', 'nuevos_expedientes.cui',
                    'nuevos_expedientes.nombre_asociado', 'nuevos_expedientes.id_agencia', 'nuevos_expedientes.numero_documento',
                    'nuevos_expedientes.usuario_asesor', 'nuevos_expedientes.tasa_interes', 'nuevos_expedientes.monto_documento',
                    'nuevos_expedientes.tipo_garantia', 'nuevos_expedientes.fecha_inicio', 'te1.nombre as estado_principal',
                    'te2.nombre as estado_secundario', 'u_bufete.name as nombre_bufete', 'se.tipo_contrato',
                    'se.numero_contrato', 'se.path_contrato', 'se.recibi_garantia_real', 'se.recibi_contrato',
                    'se.observacion_legal', 'se.archivado_at', 'sf.f_enviado_secretaria', 'sf.f_retorno_asesores',
                    'sf.f_aceptado_secretaria', 'sf.f_enviado_archivos', 'sf.f_enviado_protocolos', 'sf.f_almacenado_admin',
                    'sf.f_aceptado_secretaria_credito', 'sf.f_enviado_abogado', 'sf.f_aceptado_abogado', 'sf.f_enviado_secretaria_credito'
                )
                ->orderBy('nuevos_expedientes.id', 'DESC');

            $totalRecords = $query->count();
            $processedRecords = 0;
            $chunkSize = 1000;

            if ($totalRecords === 0) {
                $reporte->update(['progreso_porcentaje' => 100]);
            } else {
                $query->chunk($chunkSize, function ($expedientes) use ($file, &$processedRecords, $totalRecords, $reporte) {
                    foreach ($expedientes as $row) {
                        fputcsv($file, [
                            $row->id, $row->codigo_cliente, $row->cui, $row->nombre_asociado,
                            $row->id_agencia, $row->numero_documento, $row->usuario_asesor,
                            $row->tasa_interes, $row->monto_documento, $row->tipo_garantia,
                            $row->fecha_inicio, $row->estado_principal, $row->estado_secundario,
                            $row->nombre_bufete, $row->recibi_garantia_real ? 'SI' : 'NO',
                            $row->recibi_contrato ? 'SI' : 'NO', $row->tipo_contrato,
                            $row->numero_contrato, !empty($row->path_contrato) ? 'SI' : '',
                            $row->observacion_legal, $row->f_enviado_secretaria, $row->f_retorno_asesores,
                            $row->f_aceptado_secretaria, $row->f_enviado_archivos, $row->f_enviado_protocolos,
                            $row->f_almacenado_admin, $row->f_aceptado_secretaria_credito, $row->f_enviado_abogado,
                            $row->f_aceptado_abogado, $row->f_enviado_secretaria_credito, $row->archivado_at
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
            Log::error('Error generando reporte de seguimiento Asincrono: ' . $e->getMessage());
            $reporte->update([
                'estado' => 'fallido',
                'error_msg' => 'Fallo la generación: ' . $e->getMessage()
            ]);
        }
    }
}
