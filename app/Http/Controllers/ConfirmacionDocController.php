<?php

namespace App\Http\Controllers;

use App\Models\ConfirmacionDocumento;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfirmacionDocController extends Controller
{
    /**
     * Search for a document by number and date.
     */
    public function search(Request $request)
    {
        $numero = $request->input('numero');
        $fecha = $request->input('fecha');

        if (!$numero || !$fecha) {
            return response()->json(['message' => 'Número y fecha son requeridos.'], 400);
        }

        $documento = Documento::with(['tipoDocumento', 'registroPropiedad'])
            ->where('numero', $numero)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($documento) {
            // Transform to match the structure expected by frontend even if not fully confirmed yet
            return response()->json([
                'found' => true,
                'data' => [
                    'id' => $documento->id,
                    'numero' => $documento->numero,
                    'fecha' => date('Y-m-d', strtotime($documento->fecha)), // Format date for input
                    'propietario' => $documento->propietario,
                    'autorizador' => $documento->autorizador,
                    'no_finca' => $documento->no_finca,
                    'folio' => $documento->folio,
                    'libro' => $documento->libro,
                    'no_dominio' => $documento->no_dominio,
                    'referencia' => $documento->referencia,
                    'monto_poliza' => $documento->monto_poliza,
                    'observacion' => $documento->observacion,
                    'tipo_documento' => $documento->tipoDocumento?->nombre,
                    'registro_propiedad' => $documento->registroPropiedad?->nombre,
                ]
            ]);
        }

        return response()->json(['found' => false, 'data' => null]);
    }

    /**
     * Store a confirmation request (User side).
     * Confirmacion and fecha_consulta are null initially.
     */
    public function store(Request $request)
    {
        $request->validate([
            'numero' => 'required',
            'fecha' => 'required|date',
            // No validation for confirmacion/fecha_consulta as they are admin fields
        ]);

        try {
            DB::beginTransaction();

            // Create with pending status (null fields)
            $confirmacion = ConfirmacionDocumento::create($request->all());

            DB::commit();

            return response()->json(['message' => 'Solicitud de confirmación enviada exitosamente.', 'data' => $confirmacion], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List pending confirmations (Admin side).
     */
    public function index(Request $request)
    {
        // Get all where confirmacion is NULL
        $pendientes = ConfirmacionDocumento::with(['documento.tipoDocumento', 'documento.registroPropiedad'])
            ->whereNull('confirmacion')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $pendientes]);
    }

    /**
     * Update confirmation (Admin side - Validation).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'confirmacion' => 'required|in:SI,NO',
            'observacion_confirmacion' => 'nullable|string',
        ]);

        try {
            $confirmacion = ConfirmacionDocumento::findOrFail($id);

            $confirmacion->update([
                'confirmacion' => $request->confirmacion,
                'observacion_confirmacion' => $request->observacion_confirmacion,
                'fecha_consulta' => now(), // Set validation timestamp
            ]);

            return response()->json(['message' => 'Documento validado correctamente.', 'data' => $confirmacion]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al validar documento: ' . $e->getMessage()], 500);
        }
    }
}
