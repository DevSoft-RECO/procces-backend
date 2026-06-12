<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\SeguimientoExpediente;
use Illuminate\Http\Request;

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
        $documento = Documento::findOrFail($id);

        // Marcar expedientes como modificados antes de desvincular y borrar
        SeguimientoExpediente::marcarModificacionPorDocumento($id);

        // Desvincular de los expedientes (tabla pivot)
        $documento->nuevosExpedientes()->detach();

        // Eliminar el documento
        $documento->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente'
        ]);
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
