<?php

namespace App\Http\Controllers;

use App\Models\DetalleGarantia;
use Illuminate\Http\Request;

class DetalleGarantiaEdicionController extends Controller
{
    /**
     * Search for DetalleGarantia records.
     */
    public function search(Request $request)
    {
        $request->validate([
            'search' => 'required|string',
        ]);

        $search = $request->input('search');

        // Search by nuevo_expediente_id (matches codigo_cliente) or garantia_id
        $detalles = DetalleGarantia::with(['nuevoExpediente', 'garantia'])
            ->where('nuevo_expediente_id', 'like', "%{$search}%")
            ->orWhere('garantia_id', 'like', "%{$search}%")
            ->get();

        return response()->json($detalles);
    }

    /**
     * Update a DetalleGarantia record.
     */
    public function update(Request $request, $id)
    {
        $detalle = DetalleGarantia::findOrFail($id);

        $validatedData = $request->validate([
            'garantia_id' => 'sometimes|required|exists:garantias,id',
            'codeudor1' => 'nullable|string',
            'codeudor2' => 'nullable|string',
            'codeudor3' => 'nullable|string',
            'codeudor4' => 'nullable|string',
            'observacion1' => 'nullable|string',
            'observacion2' => 'nullable|string',
            'observacion3' => 'nullable|string',
            'observacion4' => 'nullable|string',
        ]);

        $detalle->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Detalle de garantía actualizado correctamente.',
            'data' => $detalle
        ]);
    }
}
