<?php

namespace App\Http\Controllers;

use App\Models\SolicitudAdministrativa;
use Illuminate\Http\Request;

class SolicitudAdministrativaEdicionController extends Controller
{
    /**
     * Search for SolicitudAdministrativa records by id_expediente or numero_documento.
     */
    public function search(Request $request)
    {
        $request->validate([
            'search' => 'required|string',
        ]);

        $search = $request->input('search');

        $solicitudes = SolicitudAdministrativa::with(['expediente', 'usuarioSolicita', 'agencia', 'usuarioDespacho'])
            ->where('id_expediente', $search)
            ->orWhereHas('expediente', function($q) use ($search) {
                $q->where('numero_documento', 'like', "%{$search}%");
            })
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Update a SolicitudAdministrativa record.
     */
    public function update(Request $request, $id)
    {
        $solicitud = SolicitudAdministrativa::findOrFail($id);

        $validatedData = $request->validate([
            'id_agencia' => 'sometimes|required|exists:agencias,id',
            'id_usuario_solicita' => 'sometimes|required|exists:users,id',
            'fecha_solicitud' => 'sometimes|required|date',
            'estado_solicitud' => 'sometimes|required|string',
            'id_usuario_despacho' => 'nullable|exists:users,id',
            'fecha_despacho' => 'nullable|date',
            'confirmacion_solicitante' => 'sometimes|required|string',
            'fecha_devolucion_iniciada' => 'nullable|date',
            'confirmacion_reingreso' => 'sometimes|required|string',
            'fecha_finalizacion' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'observacion_despacho' => 'nullable|string',
            'estado' => 'sometimes|required|string',
        ]);

        $solicitud->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud administrativa actualizada correctamente.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * Get catalogs for the edit form.
     */
    public function getCatalogs()
    {
        $agencias = \App\Models\Agencia::select('id', 'nombre')->orderBy('nombre', 'asc')->get();
        $usuarios = \App\Models\User::select('id', 'name', 'username')->orderBy('name', 'asc')->get();

        return response()->json([
            'agencias' => $agencias,
            'usuarios' => $usuarios,
        ]);
    }
}
