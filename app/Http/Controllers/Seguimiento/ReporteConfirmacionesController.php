<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteConfirmacionesJob;

class ReporteConfirmacionesController extends Controller
{
    /**
     * Dispatch general confirmaciones background report job.
     */
    public function dispatchReport(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $descarga = ReporteExportacion::create([
            'usuario_id' => $user->id,
            'tipo_reporte' => 'general_confirmaciones',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        GenerarReporteConfirmacionesJob::dispatch($descarga->id, $user->id);

        return response()->json([
            'message' => 'Reporte de Confirmaciones encolado. Procesando en segundo plano.',
            'descarga_id' => $descarga->id
        ], 202);
    }
}
