<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteGeneralAgenciaJob;

class ReporteGeneralAgenciaController extends Controller
{
    /**
     * Inicia la petición asíncrona de generación de reporte general por agencias.
     */
    public function dispatchReport(Request $request)
    {
        $request->validate([
            'agencias' => 'nullable|array', // Puede venir null o un array de agencias. Si es null o vacío = Todas.
            'agencias.*' => 'integer'
        ]);

        $agenciasSeleccionadas = $request->input('agencias', []);

        // 1. Crear el registro en la tabla de exportaciones para trackeo
        $reporte = ReporteExportacion::create([
            'usuario_id' => auth()->id() ?? 1,
            'tipo_reporte' => 'general_agencias',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        // 2. Enviar el Job a la cola con los identificadores de agencias requeridas
        GenerarReporteGeneralAgenciaJob::dispatch($reporte->id, $agenciasSeleccionadas);

        return response()->json([
            'success' => true,
            'message' => 'Reporte general de agencias enviado a la cola de procesamiento.',
            'reporte_id' => $reporte->id
        ], 202);
    }
}
