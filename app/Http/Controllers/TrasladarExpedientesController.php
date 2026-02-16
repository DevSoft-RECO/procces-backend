<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use Illuminate\Support\Facades\DB;

class TrasladarExpedientesController extends Controller
{
    /**
     * Buscar expediente por ID o Número de Documento.
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1'
        ]);

        $query = $request->input('query');

        $expediente = NuevoExpediente::with(['agencia', 'garantias', 'documentos'])
            ->where('id', $query)
            ->orWhere('numero_documento', $query)
            ->first();

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expediente
        ]);
    }

    /**
     * Actualizar el asesor de un expediente.
     */
    public function updateAsesor(Request $request, $id)
    {
        $request->validate([
            'usuario_asesor' => 'required|string|max:255'
        ]);

        $expediente = NuevoExpediente::findOrFail($id);

        try {
            DB::beginTransaction();

            $expediente->usuario_asesor = $request->usuario_asesor;
            $expediente->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente trasladado correctamente al asesor: ' . $request->usuario_asesor
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al trasladar expediente: ' . $e->getMessage()
            ], 500);
        }
    }
}
