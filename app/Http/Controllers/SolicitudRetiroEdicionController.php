<?php

namespace App\Http\Controllers;

use App\Models\SolicitudRetiro;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SolicitudRetiroEdicionController extends Controller
{
    /**
     * Search for SolicitudRetiro records by numero_documento and optionally fecha_documento.
     */
    public function search(Request $request)
    {
        $request->validate([
            'numero_documento' => 'required|string',
            'fecha_documento' => 'nullable|date',
        ]);

        $query = SolicitudRetiro::with(['expediente', 'agencia', 'solicitante', 'despachador', 'entregador', 'agenciaEntrega', 'expedienteHistorico'])
            ->where('numero_documento', $request->numero_documento);

        if ($request->fecha_documento) {
            $query->whereDate('fecha_documento', $request->fecha_documento);
        }

        $results = $query->get();

        return response()->json($results);
    }

    /**
     * Update a SolicitudRetiro record.
     */
    public function update(Request $request, $id)
    {
        $solicitud = SolicitudRetiro::findOrFail($id);

        $validatedData = $request->validate([
            'id_expediente' => 'nullable|exists:nuevos_expedientes,id',
            'id_expediente_historico' => 'nullable|exists:expedientes,id',
            'numero_documento' => 'sometimes|required|string',
            'fecha_documento' => 'nullable|date',
            'id_documento' => 'nullable|exists:documentos,id',
            'titulo_nombre' => 'sometimes|required|string',
            'id_agencia' => 'sometimes|required|exists:agencias,id',
            'id_usuario_solicitante' => 'sometimes|required|exists:users,id',
            'tipo_retiro' => 'sometimes|required|in:Temporal,Definitivo',
            'justificacion' => 'sometimes|required|string',
            'fecha_solicitud' => 'sometimes|required|date',
            'id_usuario_despacho' => 'nullable|exists:users,id',
            'fecha_envio' => 'nullable|date',
            'id_usuario_entrega' => 'nullable|exists:users,id',
            'id_agencia_entrega' => 'nullable|exists:agencias,id',
            'estado_actual' => 'sometimes|required|integer',
        ]);

        $solicitud->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de retiro actualizada correctamente.',
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
