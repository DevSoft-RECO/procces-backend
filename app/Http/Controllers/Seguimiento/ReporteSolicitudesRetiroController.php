<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteSolicitudesRetiroJob;

class ReporteSolicitudesRetiroController extends Controller
{
    /**
     * Inicia la petición asíncrona de generación de reporte completo de Solicitudes de Retiro.
     */
    public function dispatchReport(Request $request)
    {
        $reporte = ReporteExportacion::create([
            'usuario_id' => auth()->id() ?? 1,
            'tipo_reporte' => 'general_solicitudes_retiros',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        GenerarReporteSolicitudesRetiroJob::dispatch($reporte->id);

        return response()->json([
            'success' => true,
            'message' => 'Reporte general de retiros de garantía enviado a la cola.',
            'reporte_id' => $reporte->id
        ], 202);
    }
}
