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

class GenerarReporteSolicitudesRetiroJob implements ShouldQueue
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
        Log::info("INICIANDO JOB RETIROS_GARANTIAS - Reporte ID: {$this->reporteId}");

        $reporte = ReporteExportacion::find($this->reporteId);
        if (!$reporte) {
            Log::error("JOB RETIROS_GARANTIAS FALLIDO: No se encontró reporte ID {$this->reporteId}");
            return;
        }

        try {
            $reporte->update(['estado' => 'procesando', 'progreso_porcentaje' => 5]);

            $fileName = 'general_solicitudes_retiros_' . time() . '_' . uniqid() . '.csv';
            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF8

            $columns = [
                'ID Solicitud', 'Código Cliente', 'Número Producto',
                'Número de Documento', 'Título/Nombre', 'Fecha del Documento',
                'Agencia Remitente', 'Usuario Solicitante', 'Tipo de Retiro', 'Justificación',
                'Fecha de Solicitud', 'Usuario que Autoriza (Despachador)',
                'Fecha de Envío', 'Usuario de Entrega (Agencia)', 'Agencia Entregada',
                'Ruta de Evidencia (PDF/IMG)', 'Estado Actual',
                'Fecha de Retorno a Central', 'Usuario que Envió a Central', 'Observación de Retorno',
                'Fecha Confirmada de Ingreso', 'Usuario Central (Recibió Física)',
                'Fecha Creación Fila', 'Última Modificación'
            ];
            fputcsv($file, $columns, ',', '"', '"');

            $query = DB::table('solicitudes_expedientes')
                ->leftJoin('users as u_solicita', 'solicitudes_expedientes.id_usuario_solicitante', '=', 'u_solicita.id')
                ->leftJoin('users as u_despacha', 'solicitudes_expedientes.id_usuario_despacho', '=', 'u_despacha.id')
                ->leftJoin('users as u_entrega', 'solicitudes_expedientes.id_usuario_entrega', '=', 'u_entrega.id')
                ->leftJoin('users as u_retorno', 'solicitudes_expedientes.id_usuario_retorno', '=', 'u_retorno.id')
                ->leftJoin('users as u_confirma', 'solicitudes_expedientes.id_usuario_confirmacion_retorno', '=', 'u_confirma.id')
                ->leftJoin('agencias as a_remite', 'solicitudes_expedientes.id_agencia', '=', 'a_remite.id')
                ->leftJoin('agencias as a_entrega', 'solicitudes_expedientes.id_agencia_entrega', '=', 'a_entrega.id')
                ->select(
                    'solicitudes_expedientes.id',
                    'solicitudes_expedientes.codigo_cliente',
                    'solicitudes_expedientes.numero_producto',
                    'solicitudes_expedientes.numero_documento',
                    'solicitudes_expedientes.titulo_nombre',
                    'solicitudes_expedientes.fecha_documento',
                    'a_remite.nombre as agencia_origen',
                    'u_solicita.name as nombre_solicitante',
                    'solicitudes_expedientes.tipo_retiro',
                    'solicitudes_expedientes.justificacion',
                    'solicitudes_expedientes.fecha_solicitud',
                    'u_despacha.name as despachador',
                    'solicitudes_expedientes.fecha_envio',
                    'u_entrega.name as agente_entrega',
                    'a_entrega.nombre as agencia_destino',
                    'solicitudes_expedientes.evidencia_entrega_path',
                    'solicitudes_expedientes.estado_actual',
                    'solicitudes_expedientes.fecha_retorno',
                    'u_retorno.name as agente_retorno',
                    'solicitudes_expedientes.observacion_retorno',
                    'solicitudes_expedientes.fecha_confirmacion_retorno',
                    'u_confirma.name as agente_confirmacion_retorno',
                    'solicitudes_expedientes.created_at',
                    'solicitudes_expedientes.updated_at'
                )
                ->orderBy('solicitudes_expedientes.id', 'DESC');

            $totalRecords = $query->count();
            $processedRecords = 0;
            $chunkSize = 1000;

            $reporte->update(['progreso_porcentaje' => 10]);

            $query->chunk($chunkSize, function ($retiros) use ($file, &$processedRecords, $totalRecords, $reporte) {
                foreach ($retiros as $row) {

                    // Tratamiento de Retiro enum a Texto Amigable
                    $tipoReg = $row->tipo_retiro;
                    if ($tipoReg === 'definitiva' || $tipoReg === 'Definitivo') $tipoReg = 'DEFINITIVO';
                    elseif ($tipoReg === 'temporal' || $tipoReg === 'Temporal') $tipoReg = 'TEMPORAL';

                    // Tratamiento del Estado
                    $estadoStr = $row->estado_actual;
                    if ($estadoStr == 1) $estadoStr = "Solicitud Pendiente";
                    elseif ($estadoStr == 2) $estadoStr = "Despachado (Temporal)";
                    elseif ($estadoStr == 3) $estadoStr = "Despachado (Definitivo)";
                    elseif ($estadoStr == 4) $estadoStr = "Recibido en Agencia (Físico)";
                    elseif ($estadoStr == 5) $estadoStr = "Entregado a Asociado (Firmado)";
                    elseif ($estadoStr == 6) $estadoStr = "En Vía de Retorno a Archivo";
                    elseif ($estadoStr == 0) $estadoStr = "Finalizado / Archivado en Central";
                    else $estadoStr = "Desconocido ({$estadoStr})";

                    $fechaDoc = $row->fecha_documento ? date('Y-m-d', strtotime($row->fecha_documento)) : null;

                    fputcsv($file, [
                        $row->id,
                        $row->codigo_cliente ?? 'SIN CÓDIGO',
                        $row->numero_producto ?? 'SIN PRODUCTO',
                        $row->numero_documento,
                        $row->titulo_nombre ?? 'SIN TÍTULO',
                        $fechaDoc,
                        $row->agencia_origen ?? 'SIN AGENCIA',
                        $row->nombre_solicitante ?? 'USUARIO BORRADO',
                        $tipoReg,
                        $row->justificacion,
                        $row->fecha_solicitud,
                        $row->despachador ?? 'SIN ASIGNAR',
                        $row->fecha_envio,
                        $row->agente_entrega ?? 'SIN ASIGNAR',
                        $row->agencia_destino ?? 'SIN AGENCIA DESTINO',
                        $row->evidencia_entrega_path,
                        $estadoStr,
                        $row->fecha_retorno,
                        $row->agente_retorno ?? 'SIN ASIGNAR',
                        $row->observacion_retorno,
                        $row->fecha_confirmacion_retorno,
                        $row->agente_confirmacion_retorno ?? 'SIN ASIGNAR',
                        $row->created_at,
                        $row->updated_at
                    ], ',', '"', '"');
                }
                $processedRecords += count($retiros);
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
            Log::info("JOB RETIROS_GARANTIAS FINALIZADO EXITOSAMENTE - Reporte ID: {$this->reporteId}");
        } catch (\Exception $e) {
            if (isset($file) && is_resource($file)) fclose($file);
            Log::error('Fallo creando reporte de Retiros de Garantias: ' . $e->getMessage());
            $reporte->update([
                'estado' => 'fallido',
                'error_msg' => 'Error al generar CSV: ' . substr($e->getMessage(), 0, 200)
            ]);
        }
    }
}
