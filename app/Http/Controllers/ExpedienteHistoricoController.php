<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expediente;

class ExpedienteHistoricoController extends Controller
{
    /**
     * List expedientes with pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Removed validation restrictions as requested
        // Just return paginated data ordered by creation
        $expedientes = Expediente::orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Search for a client by CUI/DPI.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $request->validate([
            'cui' => 'required|string',
        ]);

        $expediente = Expediente::where('cui', $request->cui)->first();

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expediente
        ]);
    }

    /**
     * Search for a client by Codigo Cliente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function searchByCodigo(Request $request)
    {
        $request->validate([
            'contrato' => 'required|string',
        ]);

        $expediente = Expediente::where('contrato', $request->contrato)->first();

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expediente
        ]);
    }
    /**
     * Update specific fields of an expediente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $expediente = Expediente::find($id);

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado'
            ], 404);
        }

        // Filter only allowed fields
        $data = $request->only([
            'inscripcion_otros_contratos',
            'inventario',
            'salida',
            'observacion',
            'estado'
        ]);

        try {
            $expediente->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Expediente actualizado correctamente',
                'data' => $expediente
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar expediente: ' . $e->getMessage()
            ], 500);
        }
    }
}
