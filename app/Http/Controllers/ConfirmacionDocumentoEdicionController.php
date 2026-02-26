<?php

namespace App\Http\Controllers;

use App\Models\ConfirmacionDocumento;
use Illuminate\Http\Request;

class ConfirmacionDocumentoEdicionController extends Controller
{
    /**
     * Search for ConfirmacionDocumento records by numero and date.
     */
    public function search(Request $request)
    {
        $request->validate([
            'numero' => 'required|string',
            'fecha' => 'nullable|date',
        ]);

        $query = ConfirmacionDocumento::with(['documento.tipoDocumento', 'documento.registroPropiedad', 'user'])
            ->where('numero', $request->numero);

        if ($request->fecha) {
            $query->whereDate('fecha', $request->fecha);
        }

        $results = $query->get();

        return response()->json($results);
    }

    /**
     * Update a ConfirmacionDocumento record.
     */
    public function update(Request $request, $id)
    {
        $confirmacion = ConfirmacionDocumento::findOrFail($id);

        $validatedData = $request->validate([
            'documento_id' => 'nullable|exists:documentos,id',
            'user_id' => 'sometimes|required|exists:users,id',
            'numero' => 'sometimes|required|string',
            'fecha' => 'sometimes|required|date',
            'propietario' => 'nullable|string',
            'autorizador' => 'nullable|string',
            'no_finca' => 'nullable|string',
            'folio' => 'nullable|string',
            'libro' => 'nullable|string',
            'no_dominio' => 'nullable|string',
            'referencia' => 'nullable|string',
            'monto_poliza' => 'nullable|numeric',
            'observacion' => 'nullable|string',
            'tipo_documento' => 'nullable|string',
            'registro_propiedad' => 'nullable|string',
            'confirmacion' => 'nullable|in:SI,NO',
            'observacion_confirmacion' => 'nullable|string',
            'fecha_confirmacion' => 'nullable|date',
            'archivado' => 'sometimes|required|boolean',
        ]);

        $confirmacion->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Confirmación de documento actualizada correctamente.',
            'confirmacion' => $confirmacion
        ]);
    }

    /**
     * Get catalogs for the edit form.
     */
    public function getCatalogs()
    {
        $usuarios = \App\Models\User::select('id', 'name', 'username')->orderBy('name', 'asc')->get();

        return response()->json([
            'usuarios' => $usuarios,
        ]);
    }
}
