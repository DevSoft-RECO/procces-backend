<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteDocumentosJob;

class ReporteDocumentosController extends Controller
{
    /**
     * Inicia la petición asíncrona de generación de reporte completo de Documentos/Garantías.
     */
    public function dispatchReport(Request $request)
    {
        // 1. Crear el registro en la tabla de exportaciones para trackeo
        $reporte = ReporteExportacion::create([
            'usuario_id' => auth()->id() ?? 1,
            'tipo_reporte' => 'general_documentos',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        // 2. Enviar el Job a la cola
        GenerarReporteDocumentosJob::dispatch($reporte->id);

        return response()->json([
            'success' => true,
            'message' => 'Reporte general de documentos y garantías enviado a la cola de procesamiento.',
            'reporte_id' => $reporte->id
        ], 202);
    }
}
