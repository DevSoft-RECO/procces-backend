<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteGeneralAsesorJob;

class ReporteGeneralAsesorController extends Controller
{
    /**
     * Inicia la petición asíncrona de generación de reporte general por un Asesor específico.
     */
    public function dispatchReport(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255'
        ]);

        $username = $request->input('username');

        // 1. Crear el registro en la tabla de exportaciones para trackeo
        $reporte = ReporteExportacion::create([
            'usuario_id' => auth()->id() ?? 1,
            'tipo_reporte' => 'general_asesor',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        // 2. Enviar el Job a la cola pasándole el target "username"
        GenerarReporteGeneralAsesorJob::dispatch($reporte->id, $username);

        return response()->json([
            'success' => true,
            'message' => 'Reporte general del asesor enviado a la cola de procesamiento.',
            'reporte_id' => $reporte->id
        ], 202);
    }
}
