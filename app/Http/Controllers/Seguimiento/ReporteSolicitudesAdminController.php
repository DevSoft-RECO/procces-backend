<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteSolicitudesAdminJob;

class ReporteSolicitudesAdminController extends Controller
{
    /**
     * Inicia la petición asíncrona de generación de reporte completo de Prestamos/Solicitudes Admin.
     */
    public function dispatchReport(Request $request)
    {
        $reporte = ReporteExportacion::create([
            'usuario_id' => auth()->id() ?? 1,
            'tipo_reporte' => 'general_solicitudes_admin',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        GenerarReporteSolicitudesAdminJob::dispatch($reporte->id);

        return response()->json([
            'success' => true,
            'message' => 'Reporte general de préstamos de expediente enviado a la cola.',
            'reporte_id' => $reporte->id
        ], 202);
    }
}
