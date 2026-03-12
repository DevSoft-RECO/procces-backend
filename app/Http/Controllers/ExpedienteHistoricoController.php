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
        $query = Expediente::query();

        // Filter by Agency
        if ($request->filled('agencia')) {
            $query->where('agencia', $request->agencia);
        }

        // Filter by Status
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Sorting by Date (fecha_inicio)
        $order = $request->input('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('fecha_inicio', $order);

        $expedientes = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Get unique agencies from the database.
     */
    public function getAgencias()
    {
        $agencias = \App\Models\Agencia::orderBy('nombre', 'asc')->get();

        return response()->json($agencias);
    }

    /**
     * Get unique statuses from the expedientes table.
     */
    public function getEstados()
    {
        $estados = Expediente::select('estado')
            ->whereNotNull('estado')
            ->where('estado', '!=', '')
            ->distinct()
            ->orderBy('estado', 'asc')
            ->pluck('estado');

        return response()->json([
            'success' => true,
            'data' => $estados
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

        $termino = $request->contrato;

        $expedientes = Expediente::where('numero_documento', $termino)
            ->orWhere('cta_bw', $termino)
            ->orWhere('codigo_cliente', $termino)
            ->orWhere('asociado', 'LIKE', "%{$termino}%")
            ->get();

        if ($expedientes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expedientes
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
            'estado',
            'localizacion',
            'contrato',
            'datos_garantia',
            'ingreso'
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
