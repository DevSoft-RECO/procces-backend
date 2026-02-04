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
     * Obtener detalles completos (Garantías y Documentos).
     */
    public function getDetalles($codigoCliente)
    {
        $expediente = NuevoExpediente::with(['garantias', 'documentos.tipoDocumento', 'documentos.registroPropiedad'])
                        ->findOrFail($codigoCliente);

        return response()->json([
            'success' => true,
            'data' => [
                'expediente' => $expediente,
                'garantias' => $expediente->garantias,
                'documentos' => $expediente->documentos
            ]
        ]);
    }

    /**
     * Verificar si existen documentos con número y fecha.
     */
    public function checkDocumento(Request $request)
    {
        $request->validate([
            'numero' => 'required|string',
            'fecha' => 'required|date'
        ]);

        $documentos = \App\Models\Documento::where('numero', $request->numero)
                        ->where('fecha', $request->fecha)
                        ->with('tipoDocumento', 'registroPropiedad')
                        ->get(); // Get ALL matches

        if ($documentos->isNotEmpty()) {
            // Check association for each document
            $mappedDocs = $documentos->map(function ($doc) use ($request) {
                $alreadyLinked = false;
                if ($request->has('nuevo_expediente_id')) {
                    $alreadyLinked = $doc->nuevosExpedientes()
                                       ->where('nuevos_expedientes.codigo_cliente', $request->nuevo_expediente_id)
                                       ->exists();
                }
                $doc->already_linked = $alreadyLinked;
                return $doc;
            });

            return response()->json([
                'success' => true,
                'found' => true,
                'data' => $mappedDocs
            ]);
        }

        return response()->json([
            'success' => true,
            'found' => false,
            'data' => []
        ]);
    }

    /**
     * Crear y asociar un documento a un expediente nuevo.
     * Si se envía 'documento_id', solo asocia.
     */
    public function addDocumento(Request $request, $codigoCliente)
    {
        $expediente = NuevoExpediente::findOrFail($codigoCliente);

        try {
            DB::beginTransaction();

            $docId = $request->input('documento_id');
            $action = 'vinculado';

            if ($docId) {
                // Vincular existente
                $expediente->documentos()->syncWithoutDetaching([$docId]);
            } else {
                // Crear nuevo
                $request->validate([
                    'tipo_documento_id' => 'required|exists:tipo_documentos,id',
                    'registro_propiedad_id' => 'required|exists:registro_propiedads,id',
                    'numero' => 'nullable|string|max:30',
                    // ... other validations are implicitly handled by create if data passed correctly
                ]);

                $documento = \App\Models\Documento::create($request->all());
                $expediente->documentos()->attach($documento->id);
                $action = 'creado y vinculado';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Documento {$action} correctamente al expediente."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el documento: ' . $e->getMessage()
            ], 500);
        }
    }
}
