<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionSegaController extends Controller
{
    /**
     * Genera un archivo CSV con toda la información cruda de expedientes y sus fechas de seguimiento.
     */
    public function exportCSV(Request $request)
    {
        $fileName = 'seguimiento_expedientes_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function() {
            $file = fopen('php://output', 'w');

            // Escribir BOM para que Excel lea los acentos UTF-8 correctamente
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados del CSV (Mezcla de NuevoExpediente + SeguimientoExpediente + SeguimientoFecha)
            // Encabezados del CSV (Mezcla de NuevoExpediente + SeguimientoExpediente + SeguimientoFecha)
            $columns = [
                'Expediente ID',
                'Código Cliente',
                'CUI',
                'Nombre Asociado',
                'Agencia ID',
                'Número Documento',
                'Usuario Asesor',
                'Tasa Interés',
                'Monto Documento',
                'Tipo Garantía ID',
                'Fecha Inicio',

                // Datos Seguimiento (Tracking)
                'Estado Actual',
                'Estado Secundario',
                'Bufete Asignado',
                'Recibí Garantía Real',
                'Recibí Contrato',
                'Tipo Contrato',
                'Número Contrato',
                'Contrato escaneado',
                'Observación Legal',

                // Fechas de Tracking (Orden Cronológico Requerido)
                'Fec. Enviado Secretaría',
                'Fec. Retorno Asesores',
                'Fec. Aceptado Secretaría',
                'Fec. Enviado Archivos',
                'Fec. Enviado Protocolos',
                'Fec. Almacenado Admin',
                'Fec. Aceptado Sec. Crédito',
                'Fec. Enviado Abogado',
                'Fec. Aceptado Abogado',
                'Fec. Enviado Secret. Crédito',
                'Archivados (Finalizado)'
            ];

            fputcsv($file, $columns);

            // Obtener los datos usando JOINs eficientes
            DB::table('nuevos_expedientes')
                ->leftJoin('seguimiento_expedientes as se', 'nuevos_expedientes.id', '=', 'se.id_expediente')
                ->leftJoin('seguimiento_fechas as sf', 'nuevos_expedientes.id', '=', 'sf.id_expediente')
                ->leftJoin('tipo_estados as te1', 'se.id_estado', '=', 'te1.id') // Join para el nombre del estado
                ->leftJoin('tipo_estados as te2', 'se.id_estado_secundario', '=', 'te2.id')
                ->leftJoin('bufetes', 'se.bufete_id', '=', 'bufetes.id')
                ->leftJoin('users as u_bufete', 'bufetes.user_id', '=', 'u_bufete.id') // Join final para sacar el nombre del Abogado/Bufete
                ->select(
                    // Expediente Core
                    'nuevos_expedientes.id',
                    'nuevos_expedientes.codigo_cliente',
                    'nuevos_expedientes.cui',
                    'nuevos_expedientes.nombre_asociado',
                    'nuevos_expedientes.id_agencia',
                    'nuevos_expedientes.numero_documento',
                    'nuevos_expedientes.usuario_asesor',
                    'nuevos_expedientes.tasa_interes',
                    'nuevos_expedientes.monto_documento',
                    'nuevos_expedientes.tipo_garantia',
                    'nuevos_expedientes.fecha_inicio',

                    // Seguimiento Estado
                    'te1.nombre as estado_principal',
                    'te2.nombre as estado_secundario',
                    'u_bufete.name as nombre_bufete',
                    'se.tipo_contrato',
                    'se.numero_contrato',
                    'se.path_contrato',
                    'se.recibi_garantia_real',
                    'se.recibi_contrato',
                    'se.observacion_legal',
                    'se.archivado_at',

                    // Seguimiento Fechas (En orden)
                    'sf.f_enviado_secretaria',
                    'sf.f_retorno_asesores',
                    'sf.f_aceptado_secretaria',
                    'sf.f_enviado_archivos',
                    'sf.f_enviado_protocolos',
                    'sf.f_almacenado_admin',
                    'sf.f_aceptado_secretaria_credito',
                    'sf.f_enviado_abogado',
                    'sf.f_aceptado_abogado',
                    'sf.f_enviado_secretaria_credito'
                )
                // Usando chunk para no reventar la memoria si hay miles de registros
                ->orderBy('nuevos_expedientes.id', 'DESC')
                ->chunk(1000, function ($expedientes) use ($file) {
                    foreach ($expedientes as $row) {
                        fputcsv($file, [
                            $row->id,
                            $row->codigo_cliente,
                            $row->cui,
                            $row->nombre_asociado,
                            $row->id_agencia,
                            $row->numero_documento,
                            $row->usuario_asesor,
                            $row->tasa_interes,
                            $row->monto_documento,
                            $row->tipo_garantia,
                            $row->fecha_inicio,

                            $row->estado_principal,
                            $row->estado_secundario,
                            $row->nombre_bufete,
                            $row->recibi_garantia_real ? 'SI' : 'NO',
                            $row->recibi_contrato ? 'SI' : 'NO',
                            $row->tipo_contrato,
                            $row->numero_contrato,
                            !empty($row->path_contrato) ? 'SI' : '',
                            $row->observacion_legal,

                            // Fechas mapeadas en orden cronológico dictado
                            $row->f_enviado_secretaria,
                            $row->f_retorno_asesores,
                            $row->f_aceptado_secretaria,
                            $row->f_enviado_archivos,
                            $row->f_enviado_protocolos,
                            $row->f_almacenado_admin,
                            $row->f_aceptado_secretaria_credito,
                            $row->f_enviado_abogado,
                            $row->f_aceptado_abogado,
                            $row->f_enviado_secretaria_credito,
                            $row->archivado_at
                        ]);
                    }
                });

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
