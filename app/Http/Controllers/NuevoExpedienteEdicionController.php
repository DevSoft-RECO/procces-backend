<?php

namespace App\Http\Controllers;

use App\Models\NuevoExpediente;
use Illuminate\Http\Request;

class NuevoExpedienteEdicionController extends Controller
{
    /**
     * Search for NuevoExpediente records by numero_documento.
     */
    public function search(Request $request)
    {
        $request->validate([
            'numero_documento' => 'required|string',
        ]);

        $numero_documento = $request->input('numero_documento');

        $expedientes = NuevoExpediente::with(['agencia', 'asesor'])
            ->where('numero_documento', 'like', "%{$numero_documento}%")
            ->get();

        return response()->json($expedientes);
    }

    /**
     * Update a NuevoExpediente record.
     */
    public function update(Request $request, $id)
    {
        $expediente = NuevoExpediente::findOrFail($id);

        $validatedData = $request->validate([
            'codigo_cliente' => 'sometimes|required|string',
            'id_agencia' => 'sometimes|required|exists:agencias,id',
            'numero_documento' => 'sometimes|required|string',
            'usuario_asesor' => 'sometimes|required|string',
            'tasa_interes' => 'sometimes|required|numeric',
            'monto_documento' => 'sometimes|required|numeric',
            'tipo_garantia' => 'sometimes|required|string',
            'fecha_inicio' => 'sometimes|required|date',
            'cui' => 'sometimes|required|string',
            'nombre_asociado' => 'sometimes|required|string',
            'estado' => 'sometimes|required|string',
        ]);

        $expediente->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Expediente actualizado correctamente.',
            'expediente' => $expediente
        ]);
    }

    /**
     * Get catalogs for the edit form (Agencies and Advisors).
     */
    public function getCatalogs()
    {
        $agencias = \App\Models\Agencia::select('id', 'nombre')->orderBy('nombre', 'asc')->get();

        // Get unique advisors from NuevoExpediente table
        $asesores = NuevoExpediente::select('usuario_asesor')
            ->whereNotNull('usuario_asesor')
            ->distinct()
            ->get()
            ->map(function($item) {
                // Try to find the user in the User model to get the full name
                $user = \App\Models\User::where('username', $item->usuario_asesor)->first();
                return [
                    'username' => $item->usuario_asesor,
                    'name' => $user ? $user->name : $item->usuario_asesor
                ];
            });

        return response()->json([
            'agencias' => $agencias,
            'asesores' => $asesores
        ]);
    }
}
