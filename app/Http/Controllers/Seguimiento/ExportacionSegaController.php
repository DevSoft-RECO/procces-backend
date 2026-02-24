<?php

namespace App\Http\Controllers\Seguimiento;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReporteExportacion;
use App\Jobs\GenerarReporteSeguimientoCsvJob;
use Illuminate\Support\Facades\Storage;

class ExportacionSegaController extends Controller
{
    /**
     * Inicia un trabajo en segundo plano para exportar el CSV.
     */
    public function dispatchReport(Request $request)
    {
        // 1. Crear el registro en base de datos
        $reporte = ReporteExportacion::create([
            // Asumimos que el Middleware le asigna el usuario autenticado
            'usuario_id' => auth()->id() ?? 1, // Fallback si no hay auth estricta
            'tipo_reporte' => 'seguimiento_csv',
            'estado' => 'pendiente',
            'progreso_porcentaje' => 0
        ]);

        // 2. Enviar el Job a la cola
        GenerarReporteSeguimientoCsvJob::dispatch($reporte->id);

        return response()->json([
            'success' => true,
            'message' => 'Reporte enviado a la cola de procesamiento.',
            'reporte_id' => $reporte->id
        ], 202);
    }

    /**
     * Lista los reportes solicitados por el usuario para armar la bandeja frontal.
     */
    public function listReports(Request $request)
    {
        $userId = auth()->id() ?? 1;

        $reportes = ReporteExportacion::where('usuario_id', $userId)
            ->whereIn('tipo_reporte', [
                'seguimiento_csv', 'general_agencias', 'general_asesor',
                'general_documentos', 'general_solicitudes_admin', 'general_solicitudes_retiros', 'general_confirmaciones'
            ])
            ->orderBy('created_at', 'desc')
            ->take(10) // Mostrar últimos 10 de su bandeja
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reportes
        ]);
    }

    /**
     * Descarga el archivo cuando este ya ha alcanzado el estado de 'completado'.
     */
    public function downloadReport($id)
    {
        $reporte = ReporteExportacion::findOrFail($id);

        if ($reporte->estado !== 'completado' || empty($reporte->file_path)) {
            return response()->json(['message' => 'El reporte aún no está listo o falló.'], 400);
        }

        if (!Storage::disk('local')->exists($reporte->file_path)) {
            return response()->json(['message' => 'El archivo físico ya no existe en el servidor.'], 404);
        }

        return Storage::disk('local')->download($reporte->file_path);
    }

    /**
     * Elimina el reporte y su archivo físico relacionado.
     */
    public function destroy($id)
    {
        $reporte = ReporteExportacion::findOrFail($id);

        // Si tiene un filepath en disco, borrarlo permanentemente para ahorrar espacio
        if (!empty($reporte->file_path) && Storage::disk('local')->exists($reporte->file_path)) {
            Storage::disk('local')->delete($reporte->file_path);
        }

        $reporte->delete();

        return response()->json(['success' => true, 'message' => 'Reporte eliminado correctamente.']);
    }
}

