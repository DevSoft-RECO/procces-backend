<?php

namespace App\Http\Controllers;

use App\Models\SolicitudRetiro;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SolicitudRetiroEdicionController extends Controller
{
    protected $disk = 'gcs';
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
            'evidencia' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            DB::beginTransaction();

            // Handle file upload
            if ($request->hasFile('evidencia')) {
                // Delete old file if exists
                if ($solicitud->evidencia_entrega_path) {
                    Storage::disk($this->disk)->delete($solicitud->evidencia_entrega_path);
                }

                $file = $request->file('evidencia');
                $numDoc = $validatedData['numero_documento'] ?? $solicitud->numero_documento ?? 'S-N';
                $extension = $file->getClientOriginalExtension();
                $filename = "EVI-{$numDoc}-" . time() . ".{$extension}"; // Added timestamp to avoid cache/collision
                $folder = 'sadec/retiro_garantia';

                $path = Storage::disk($this->disk)->putFileAs($folder, $file, $filename);
                $validatedData['evidencia_entrega_path'] = $path;
            }

            // Remove 'evidencia' from validated data as it's not a column
            unset($validatedData['evidencia']);

            $solicitud->update($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de retiro actualizada correctamente.',
                'solicitud' => $solicitud
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la solicitud: ' . $e->getMessage(),
            ], 500);
        }
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
