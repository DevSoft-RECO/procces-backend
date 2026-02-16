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
    /**
     * Listado de expedientes nuevos (Mis Expedientes).
     */
    public function index(Request $request)
    {
        $query = NuevoExpediente::query();

        // Filtro por Tab (Estado del flujo)
        if ($request->has('tab')) {
            if ($request->tab === 'nuevos') {
                // Expedientes SIN seguimiento (aún no enviados)
                $query->doesntHave('seguimientos');
            } elseif ($request->tab === 'seguimiento') {
                // Expedientes EN seguimiento activo (NO rechazados/retornados - estado 2)
                $query->whereHas('seguimientos', function ($q) {
                    $q->where('id_estado', '!=', 2);
                });

                // Exclude Finalizados (State 11) - Ensure they move to Finalizados view
                $query->whereDoesntHave('seguimientos', function ($q) {
                    $q->where('id_estado', 11)
                      ->whereRaw('created_at = (
                          SELECT MAX(s2.created_at)
                          FROM seguimiento_expedientes as s2
                          WHERE s2.id_expediente = seguimiento_expedientes.id_expediente
                      )');
                });

            } elseif ($request->tab === 'retornados') {
                // Expedientes RETORNADOS (Estado 2)
                $query->whereHas('seguimientos', function ($q) {
                     $q->where('id_estado', 2);
                });
            }
        }

        // Lógica de búsqueda actualizada
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                    ->orWhere('numero_documento', 'like', "%{$search}%");
                });
            }

        $expedientes = $query->with(['garantias', 'documentos.tipoDocumento', 'seguimientos' => function($query) {
            $query->orderBy('id_seguimiento', 'desc')->with('estado');
        }])->orderBy('id', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Asociar una garantía a un expediente nuevo.
     */
    public function addGarantia(Request $request, $id)
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

        $expediente = NuevoExpediente::findOrFail($id);

        try {
            DB::beginTransaction();

            // Sync para asegurar SOLO UNA garantía por expediente (reemplaza anteriores si hubieran)
            $expediente->garantias()->sync([
                $request->garantia_id => [
                    'codeudor1' => $request->codeudor1,
                    'codeudor2' => $request->codeudor2,
                    'codeudor3' => $request->codeudor3,
                    'codeudor4' => $request->codeudor4,
                    'observacion1' => $request->observacion1,
                    'observacion2' => $request->observacion2,
                    'observacion3' => $request->observacion3,
                    'observacion4' => $request->observacion4,
                ]
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
     public function getGarantias($id)
     {
         $expediente = NuevoExpediente::with('garantias')->findOrFail($id);

         return response()->json([
             'success' => true,
             'data' => $expediente->garantias
         ]);
     }

    /**
     * Obtener detalles completos (Garantías y Documentos).
     */
    public function getDetalles($id)
    {
        $expediente = NuevoExpediente::with([
            'garantias',
            'fechas',
            'documentos.tipoDocumento',
            'documentos.registroPropiedad',
            'documentos' => function($query) {
                $query->with(['tipoDocumento', 'registroPropiedad'])
                      ->withCount('nuevosExpedientes');
            },
            'seguimientos' => function($query) {
                $query->orderBy('id_seguimiento', 'desc');
            }
        ])
        ->findOrFail($id);

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

    // Usamos withCount para obtener cuántos expedientes tiene cada documento
    $documentos = \App\Models\Documento::where('numero', $request->numero)
                    ->where('fecha', $request->fecha)
                    ->with('tipoDocumento', 'registroPropiedad')
                    ->withCount('nuevosExpedientes') // <--- Agregamos el conteo
                    ->get();

    if ($documentos->isNotEmpty()) {
        $mappedDocs = $documentos->map(function ($doc) use ($request) {
            $alreadyLinked = false;
            if ($request->has('nuevo_expediente_id')) {
                $alreadyLinked = $doc->nuevosExpedientes()
                                     ->where('nuevos_expedientes.id', $request->nuevo_expediente_id)
                                     ->exists();
            }
            $doc->already_linked = $alreadyLinked;
            // El frontend recibirá 'nuevos_expedientes_count' automáticamente
            return $doc;
        });

        return response()->json([
            'success' => true,
            'found' => true,
            'data' => $mappedDocs
        ]);
    }

    return response()->json(['success' => true, 'found' => false, 'data' => []]);
}

    /**
     * Crear y asociar un documento a un expediente nuevo.
     * Si se envía 'documento_id', solo asocia.
     */
public function addDocumento(Request $request, $id)
{
    $expediente = NuevoExpediente::findOrFail($id);

    try {
        DB::beginTransaction();

        $docId = $request->input('documento_id');
        $action = 'vinculado';

        if ($docId) {
            $documento = \App\Models\Documento::findOrFail($docId);

            // VALIDACIÓN DE EDICIÓN:
            // Permitir si:
            // 1. Estado es 2 (Retornado)
            // 2. O NO tiene seguimientos (Nuevo / Aún no enviado)
            $lastSeguimiento = $expediente->seguimientos()->latest()->first();
            $currentState = $lastSeguimiento ? $lastSeguimiento->id_estado : 0;
            $hasTracking = $expediente->seguimientos()->exists();

            if (($currentState == 2 || !$hasTracking) && $documento->nuevosExpedientes()->count() <= 1) {
                // Actualizamos con los datos que vienen del formulario
                $documento->update($request->all());
                $action = 'actualizado y vinculado';
            }

            $expediente->documentos()->syncWithoutDetaching([$docId]);
        } else {
            // Lógica de creación normal
            $request->validate([
                'tipo_documento_id' => 'required|exists:tipo_documentos,id',
                'registro_propiedad_id' => 'required|exists:registro_propiedads,id',
            ]);

            $documento = \App\Models\Documento::create($request->all());
            $expediente->documentos()->attach($documento->id);
            $action = 'creado y vinculado';
        }

        DB::commit();
        return response()->json(['success' => true, 'message' => "Documento {$action} correctamente."]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}

    /**
     * Desvincular un documento de un expediente.
     */
    public function detachDocumento($id, $documentoId)
    {
        $expediente = NuevoExpediente::findOrFail($id);

        try {
            $expediente->documentos()->detach($documentoId);

            return response()->json([
                'success' => true,
                'message' => 'Documento desvinculado correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desvincular el documento: ' . $e->getMessage()
            ], 500);
    }
}

    /**
     * Actualizar documento existente.
     * Restricción: Solo si está asociado a 1 o menos expedientes.
     */
    public function updateDocumento(Request $request, $id)
    {
        $documento = \App\Models\Documento::findOrFail($id);

        // Security Check: Only allow edit if linked to 1 or fewer expedientes
        // UNLESS the user has the special permission
        if ($documento->nuevosExpedientes()->count() > 1) {
            if (!$request->user()->tokenCan('editar_documentos_restringidos') &&
                !$request->user()->hasPermissionTo('editar_documentos_restringidos')) { // Check both token (if using abilities) and direct permission

                return response()->json([
                    'success' => false,
                    'message' => 'Este documento está asociado a múltiples expedientes. No se puede editar directamente.'
                ], 403);
            }
        }

        $request->validate([
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'registro_propiedad_id' => 'required|exists:registro_propiedads,id',
            'numero' => 'nullable|string|max:30',
            // Add other fields as necessary based on your model
        ]);

        try {
            $documento->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Documento actualizado correctamente.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos de la garantía en el expediente (pivot).
     */
public function updateGarantiaPivot(Request $request, $expedienteId, $garantiaId)
{
    $expediente = NuevoExpediente::findOrFail($expedienteId);

    // 1. Buscamos la garantía para verificar la regla de 'desplegables'
    $tipoGarantia = \App\Models\Garantia::findOrFail($garantiaId);

    try {
        DB::beginTransaction();

        // 2. Definimos los datos base del pivot
        $pivotData = [];

        if ($tipoGarantia->desplegables == 1) {
            // Si tiene desplegables, tomamos lo que viene del request
            $pivotData = [
                'codeudor1'    => $request->codeudor1,
                'codeudor2'    => $request->codeudor2,
                'codeudor3'    => $request->codeudor3,
                'codeudor4'    => $request->codeudor4,
                'observacion1' => $request->observacion1,
                'observacion2' => $request->observacion2,
                'observacion3' => $request->observacion3,
                'observacion4' => $request->observacion4,
            ];
        } else {
            // SI ES 0: Forzamos el borrado de cualquier dato previo enviando null
            $pivotData = [
                'codeudor1'    => null,
                'codeudor2'    => null,
                'codeudor3'    => null,
                'codeudor4'    => null,
                'observacion1' => null,
                'observacion2' => null,
                'observacion3' => null,
                'observacion4' => null,
            ];
        }

        // 3. Actualizamos la tabla pivot
        $expediente->garantias()->updateExistingPivot($garantiaId, $pivotData);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => $tipoGarantia->desplegables == 0
                ? 'Garantía actualizada (Datos adicionales limpiados por tipo de garantía).'
                : 'Garantía actualizada correctamente.'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar garantía: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Cambiar el tipo de garantía (reemplazar la garantía actual por otra).
     * Mantiene los datos del pivot (codeudores, observaciones).
     */
    public function changeGarantiaType(Request $request, $expedienteId, $garantiaId)
    {
        $request->validate([
            'nueva_garantia_id' => 'required|exists:garantias,id'
        ]);

        $expediente = NuevoExpediente::findOrFail($expedienteId);
        $nuevaGarantiaId = $request->nueva_garantia_id;

        try {
            DB::beginTransaction();

            // 1. Get current pivot data
            $currentGarantia = $expediente->garantias()->where('garantia_id', $garantiaId)->first();

            if (!$currentGarantia) {
                return response()->json(['success' => false, 'message' => 'Garantía no encontrada en este expediente.'], 404);
            }

            $pivotData = [
                'codeudor1' => $currentGarantia->pivot->codeudor1,
                'codeudor2' => $currentGarantia->pivot->codeudor2,
                'codeudor3' => $currentGarantia->pivot->codeudor3,
                'codeudor4' => $currentGarantia->pivot->codeudor4,
                'observacion1' => $currentGarantia->pivot->observacion1,
                'observacion2' => $currentGarantia->pivot->observacion2,
                'observacion3' => $currentGarantia->pivot->observacion3,
                'observacion4' => $currentGarantia->pivot->observacion4,
            ];

            // 2. Detach old
            $expediente->garantias()->detach($garantiaId);

            // 3. Attach new with old pivot data
            $expediente->garantias()->attach($nuevaGarantiaId, $pivotData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de garantía actualizado correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar tipo de garantía: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listado de expedientes FINALIZADOS (Estado 11).
     * Para Asesores de Crédito.
     */
    public function buzonFinalizados(Request $request)
    {
        $query = NuevoExpediente::query();

        // Filter by LATEST state = 11 (Finalizado / Pagare Recibido)
        $query->whereHas('seguimientos', function ($q) {
            $q->where('id_estado', 11)
              ->whereRaw('created_at = (
                  SELECT MAX(s2.created_at)
                  FROM seguimiento_expedientes as s2
                  WHERE s2.id_expediente = seguimiento_expedientes.id_expediente
              )');
        });

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%");
            });
        }

        $expedientes = $query->with(['garantias', 'documentos.tipoDocumento', 'seguimientos' => function($query) {
            $query->orderBy('id_seguimiento', 'desc')->with('estado');
        }])->orderBy('fecha_inicio', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }
}
