<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use Illuminate\Support\Facades\DB;

class NuevoExpedienteController extends Controller
{
    /**
     * Listado de expedientes nuevos (Mis Expedientes).
     */
    public function index(Request $request)
    {
        // Se puede agregar filtro por usuario si es necesario en el futuro
        // Por ahora listamos todos, ordenados por fecha de creación o inicio.
        $query = NuevoExpediente::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%");
        }

        $expedientes = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Asociar una garantía a un expediente nuevo.
     */
    public function addGarantia(Request $request, $codigoCliente)
    {
        $request->validate([
            'garantia_id' => 'required|exists:garantias,id',
            'codeudor1' => 'nullable|string|max:200',
            'codeudor2' => 'nullable|string|max:200',
            'codeudor3' => 'nullable|string|max:200',
            'codeudor4' => 'nullable|string|max:200',
            'observacion1' => 'nullable|string|max:200',
            'observacion2' => 'nullable|string|max:200',
            'observacion3' => 'nullable|string|max:200',
            'observacion4' => 'nullable|string|max:200',
        ]);

        $expediente = NuevoExpediente::findOrFail($codigoCliente);

        try {
            DB::beginTransaction();

            $expediente->garantias()->attach($request->garantia_id, [
                'codeudor1' => $request->codeudor1,
                'codeudor2' => $request->codeudor2,
                'codeudor3' => $request->codeudor3,
                'codeudor4' => $request->codeudor4,
                'observacion1' => $request->observacion1,
                'observacion2' => $request->observacion2,
                'observacion3' => $request->observacion3,
                'observacion4' => $request->observacion4,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Garantía agregada correctamente al expediente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar la garantía: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las garantías de un expediente.
     */
     public function getGarantias($codigoCliente)
     {
         $expediente = NuevoExpediente::with('garantias')->findOrFail($codigoCliente);

         return response()->json([
             'success' => true,
             'data' => $expediente->garantias
         ]);
     }

    /**
     * Crear y asociar un documento a un expediente nuevo.
     */
    public function addDocumento(Request $request, $codigoCliente)
    {
        $request->validate([
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'registro_propiedad_id' => 'required|exists:registro_propiedads,id', // Note table name check
            'numero' => 'nullable|string|max:30',
            'fecha' => 'nullable|date',
            'propietario' => 'nullable|string|max:250',
            'autorizador' => 'nullable|string|max:250',
            'no_finca' => 'nullable|string|max:50',
            'folio' => 'nullable|string|max:50',
            'libro' => 'nullable|string|max:50',
            'no_dominio' => 'nullable|string|max:50',
            'referencia' => 'nullable|string|max:200',
            'monto_poliza' => 'nullable|numeric',
            'observacion' => 'nullable|string',
        ]);

        $expediente = NuevoExpediente::findOrFail($codigoCliente);

        try {
            DB::beginTransaction();

            // 1. Create the Documento
            $documento = \App\Models\Documento::create($request->all());

            // 2. Attach usage in pivot (if this doc belongs to this expediente)
            // Note: If you want to reuse documents, you'd need a way to search/select existing ones.
            // For now, based on request "agregar documentos", we assume creation of new doc entry.
            $expediente->documentos()->attach($documento->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento agregado correctamente al expediente.',
                'data' => $documento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el documento: ' . $e->getMessage()
            ], 500);
        }
    }
}
