<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\SeguimientoExpediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentoEdicionController extends Controller
{
    /**
     * Search for documents by number and date.
     */
    public function search(Request $request)
    {
        $request->validate([
            'numero' => 'required|string',
            'fecha' => 'required|date',
        ]);

        $numero = $request->input('numero');
        $fecha = $request->input('fecha');

        $documentos = Documento::with(['tipoDocumento', 'registroPropiedad'])
            ->where('numero', $numero)
            ->whereDate('fecha', $fecha)
            ->get();

        return response()->json($documentos);
    }

    /**
     * Update the specified document.
     */
    public function update(Request $request, $id)
    {
        $documento = Documento::findOrFail($id);

        $validatedData = $request->validate([
            'numero' => 'sometimes|required|string',
            'fecha' => 'sometimes|required|date',
            'propietario' => 'sometimes|required|string',
            'autorizador' => 'nullable|string',
            'no_finca' => 'nullable|string',
            'folio' => 'nullable|string',
            'libro' => 'nullable|string',
            'no_dominio' => 'nullable|string',
            'referencia' => 'nullable|string',
            'monto_poliza' => 'nullable|numeric',
            'observacion' => 'nullable|string',
            'tipo_documento_id' => 'sometimes|required|exists:tipo_documentos,id',
            'registro_propiedad_id' => 'nullable|exists:registro_propiedads,id',
            'estado' => 'sometimes|required|string',
        ]);

        $documento->update($validatedData);

        // Marcar todos los expedientes asociados como "con corrección"
        SeguimientoExpediente::marcarModificacionPorDocumento($id);

        return response()->json([
            'message' => 'Documento actualizado correctamente',
            'documento' => $documento
        ]);
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            return response()->json([
                'message' => 'El documento no fue encontrado o ya ha sido eliminado.'
            ], 404);
        }

        // Verificar vinculaciones con otras tablas para proteger los datos históricos
        $vinculaciones = [];

        // 1. Verificar si está asociado a expedientes
        $expedientesCount = DB::table('documento_nuevo_expediente')
            ->where('documento_id', $id)
            ->count();
        if ($expedientesCount > 0) {
            $vinculaciones[] = "Expedientes vinculados: {$expedientesCount} registro(s) en 'documento_nuevo_expediente'.";
        }

        // 2. Verificar si está asociado a solicitudes de retiro
        $solicitudesCount = DB::table('solicitudes_expedientes')
            ->where('id_documento', $id)
            ->count();
        if ($solicitudesCount > 0) {
            $vinculaciones[] = "Solicitudes de retiro: {$solicitudesCount} registro(s) en 'solicitudes_expedientes'.";
        }

        // 3. Verificar si está asociado a confirmaciones de documentos
        $confirmacionesCount = DB::table('confirmaciones_documentos')
            ->where('documento_id', $id)
            ->count();
        if ($confirmacionesCount > 0) {
            $vinculaciones[] = "Confirmaciones de documentos: {$confirmacionesCount} registro(s) en 'confirmaciones_documentos'.";
        }

        // Si tiene registros vinculados, no se puede eliminar para proteger datos históricos
        if (!empty($vinculaciones)) {
            return response()->json([
                'message' => 'No se puede eliminar la garantía porque contiene registros históricos vinculados.',
                'detalles' => $vinculaciones,
                'sugerencia' => 'Para eliminarla, primero tendrían que removerse o desvincularse los registros en las tablas donde está enlazada, protegiendo así los datos históricos.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Marcar expedientes como modificados antes de desvincular y borrar (si aplicara)
            SeguimientoExpediente::marcarModificacionPorDocumento($id);

            // Desvincular de los expedientes (tabla pivot)
            $documento->nuevosExpedientes()->detach();

            // Eliminar el documento
            $documento->delete();

            DB::commit();

            return response()->json([
                'message' => 'Documento eliminado correctamente'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Error al eliminar documento {$id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al eliminar el documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created document.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'numero' => 'required|string',
            'fecha' => 'required|date',
            'propietario' => 'required|string',
            'autorizador' => 'nullable|string',
            'no_finca' => 'nullable|string',
            'folio' => 'nullable|string',
            'libro' => 'nullable|string',
            'no_dominio' => 'nullable|string',
            'referencia' => 'nullable|string',
            'monto_poliza' => 'nullable|numeric',
            'observacion' => 'nullable|string',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'registro_propiedad_id' => 'nullable|exists:registro_propiedads,id',
            'estado' => 'required|string',
        ]);

        $documento = Documento::create($validatedData);

        return response()->json([
            'message' => 'Documento creado correctamente',
            'documento' => $documento
        ], 201);
    }
}
