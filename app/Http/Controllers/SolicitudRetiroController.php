<?php

namespace App\Http\Controllers;

use App\Models\SolicitudRetiro;
use App\Models\Documento;
use App\Models\NuevoExpediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SolicitudRetiroController extends Controller
{
    /**
     * Search for a document and validate its availability.
     */
    public function search(Request $request)
    {
        $termino = $request->input('termino');

        if (!$termino) {
            return response()->json(['message' => 'Término de búsqueda requerido'], 400);
        }

        // 1. Buscar en NuevoExpediente por número de documento
        // Cargamos también los documentos asociados para validación y retorno
        $expediente = NuevoExpediente::with(['documentos.tipoDocumento', 'documentos.registroPropiedad'])
            ->where('numero_documento', $termino)
            ->latest()
            ->first();

        if (!$expediente) {
             // FALLBACK: Buscar en Expediente (Historicos)
             $historico = \App\Models\Expediente::where('numero_documento', $termino)->first();

             if ($historico) {
                 return response()->json([
                     'found' => true,
                     'source' => 'historico',
                     'data' => [
                         'numero_documento' => $historico->numero_documento,
                         'titulo_nombre' => $historico->asociado,
                         'id_expediente' => null, // No hay ID de nuevo expediente
                         'es_manual' => false,
                         'datos_garantia' => $historico->datos_garantia, // Campo clave
                         'observaciones' => $historico->observacion // Opcional
                     ]
                 ]);
             }

             // Si no existe tampoco en historicos
             // Si no existe el expediente, no hay nada que pedir (Regla estricta solicitada)
             // Aunque el frontend maneja "found: false" para manual, el usuario pidió corregir lógica base.
             // Mantendremos la respuesta de "no encontrado" pero el frontend decidirá si muestra manual o no.
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún expediente con ese número de documento (Ni actual ni histórico).',
                'data' => [
                    'numero_documento' => $termino,
                    'es_manual' => true
                ]
            ]);
        }

        // 2. CASO 3: Validar que exista en SeguimientoExpediente
        $tieneSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expediente->id)->exists();

        if (!$tieneSeguimiento) {
             return response()->json([
                'error' => true,
                'message' => 'El expediente existe pero no tiene garantías asociadas aún.',
                'bloqueado' => true
            ]);
        }

        // 3. Obtener documentos y adjuntar metadatos de estado (Sin boqueos, solo info)
        $documentosProcesados = $expediente->documentos->map(function($doc) use ($expediente) {
            // Verificar si este documento está vinculado a OTROS expedientes que estén ACTIVOS
            $otrosActivos = $doc->nuevosExpedientes()
                ->where('nuevos_expedientes.id', '!=', $expediente->id)
                ->where('nuevos_expedientes.estado', 'activo')
                ->get(['nuevos_expedientes.numero_documento', 'nuevos_expedientes.estado', 'nuevos_expedientes.nombre_asociado']);

            $doc->tiene_otros_activos = $otrosActivos->isNotEmpty();
            // Map to a structure we can easily display
            $doc->otros_activos_lista = $otrosActivos->map(function($exp) {
                return [
                    'numero' => $exp->numero_documento,
                    'nombre' => $exp->nombre_asociado
                ];
            });

            return $doc;
        });

        // 4. Liberación con lista de documentos
        return response()->json([
            'found' => true,
            'data' => [
                'numero_documento' => $expediente->numero_documento,
                'titulo_nombre' => $expediente->nombre_asociado,
                'id_expediente' => $expediente->id,
                'es_manual' => false,
                'expediente_activo' => $expediente->estado == 'activo', // Flag informativo
                'documentos' => $documentosProcesados
            ]
        ]);
    }

    /**
     * Store a new withdrawal request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'numero_documento' => 'required|string',
            'tipo_retiro' => 'required|in:Temporal,Definitivo',
            'justificacion' => 'required|string',
            'es_manual' => 'boolean',
            'id_expediente' => 'nullable|exists:nuevos_expedientes,id',
            'titulo_nombre' => 'required|string',
        ]);

        $user = Auth::user();

        // Validar nuevamente que no esté bloqueado (Security Layer)
        // Validar nuevamente que no esté bloqueado (Security Layer)
        // Validar nuevamente que no esté bloqueado (Security Layer)
        if (!$request->es_manual) {
            // Recuparar expediente contexto
            $expedienteId = $request->id_expediente;
            $numeroDoc = $request->numero_documento;

            $expediente = null;
            if ($expedienteId) {
                $expediente = NuevoExpediente::find($expedienteId);
            } else {
                $expediente = NuevoExpediente::where('numero_documento', $numeroDoc)->latest()->first();
            }

            if ($expediente) {
                // Validación 1: Estado del expediente ACTUAL
                /*
                if ($expediente->estado == 'activo') {
                    return response()->json(['message' => 'El expediente asociado aún se encuentra activo.'], 422);
                }
                */

                // Validación 2: Seguimiento
                $tieneSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expediente->id)->exists();
                if (!$tieneSeguimiento) {
                    return response()->json(['message' => 'El expediente existe pero no tiene garantías asociadas aún.'], 422);
                }

                // Validación 3: Otros Activos (Documentos compartidos)
                // Cargar documentos para verificar cruces
                /*
                $expediente->load('documentos');
                if ($expediente->documentos->isNotEmpty()) {
                    foreach ($expediente->documentos as $doc) {
                        $otrosActivos = $doc->nuevosExpedientes()
                            ->where('nuevos_expedientes.id', '!=', $expediente->id)
                            ->where('nuevos_expedientes.estado', 'activo')
                            ->exists();

                        if ($otrosActivos) {
                            return response()->json(['message' => "El documento {$doc->numero} está amarrado a otro expediente activo."], 422);
                        }
                    }
                }
                */
            } else {
                // Si no se encuentra expediente pero no es manual, es sospechoso, pero mantendremos la lógica simple
                // Si llegamos aqui es porque el search encontró algo (o es manual=false)
                // Si no encontramos expediente:
                // Validar docs huérfanos si fuera el caso, pero asumiremos consistencia con search.
            }
        }

        try {
            DB::beginTransaction();

            // Buscar el documento real para amarrar el ID físico
            $documentoId = null;
            if ($request->numero_documento) {
                $doc = \App\Models\Documento::where('numero', $request->numero_documento)->first();
                if ($doc) {
                    $documentoId = $doc->id;
                }
            }

            $solicitud = SolicitudRetiro::create([
                'id_expediente' => $request->id_expediente,
                'numero_documento' => $request->numero_documento,
                'id_documento' => $documentoId,
                'titulo_nombre' => $request->titulo_nombre,
                'id_agencia' => $request->input('id_agencia') ?? $user->id_agencia, // Priorizar request, fallback user
                'id_usuario_solicitante' => $user->id,
                'tipo_retiro' => $request->tipo_retiro,
                'justificacion' => $request->justificacion,
                'fecha_solicitud' => Carbon::now(),
                'estado_actual' => 1, // Solicitado
            ]);

            DB::commit();

            return response()->json(['message' => 'Solicitud creada correctamente', 'data' => $solicitud], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List ACTIVE requests for the Agency.
     * EXCLUDES Status 0 (Archived/Finalized).
     */
    public function indexAgency(Request $request)
    {
        $user = Auth::user();

        // Priorizar ID enviado desde frontend (Auth Store) y luego el del usuario
        $agencyId = $request->input('id_agencia') ?? $user->id_agencia ?? $user->getAgenciaId();

        // Si el usuario no tiene agencia, retornar error o vacío
        if (!$agencyId) {
             return response()->json(['data' => []]);
        }

        $solicitudes = SolicitudRetiro::where('id_agencia', $agencyId)
            ->where('estado_actual', '!=', 0) // Excluir Archivados/Finalizados
            ->with(['solicitante', 'despachador'])
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Paginación de 10 elementos

        return response()->json($solicitudes);
    }

    /**
     * List HISTORICAL (Archived/Finalized) requests for the Agency.
     * ONLY INCLUDES Status 0.
     */
    public function indexAgencyHistory(Request $request)
    {
        $user = Auth::user();

        $agencyId = $request->input('id_agencia') ?? $user->id_agencia ?? $user->getAgenciaId();

        if (!$agencyId) {
             return response()->json(['data' => []]);
        }

        $solicitudes = SolicitudRetiro::where('id_agencia', $agencyId)
            ->where('estado_actual', 0) // Solo Archivados/Finalizados
            ->with(['solicitante', 'despachador'])
            ->orderBy('updated_at', 'desc') // Ordenar por fecha de finalización
            ->paginate(10);

        return response()->json($solicitudes);
    }

    /**
     * List requests for the Archive Mailbox (Buzón).
     */
    public function indexArchive(Request $request)
    {
        // Filtros: 1=Pendientes, 2=Enviados Temporales, 3=Enviados Definitivos
        $estado = $request->input('estado');

        $query = SolicitudRetiro::with(['agencia', 'solicitante', 'documento.tipoDocumento', 'documento.registroPropiedad', 'expedienteHistorico']);

        if ($estado == 2) {
             // Enviados Temporales (Historial: Retiro Temporal y SOLO estado 2 o 4 o 5, NO 6)
             // Ajuste: El usuario no quiere ver los "En Retorno" (6) aquí.
             $query->where('tipo_retiro', 'Temporal')
                   ->whereIn('estado_actual', [2, 4, 5]); // Enviado, Recibido, Entregado (Pero no Retorno)
        } elseif ($estado == 3) {
             // Enviados Definitivos
             $query->where('tipo_retiro', 'Definitivo')
                   ->where('estado_actual', '!=', 1);
        } elseif ($estado == 4) {
             // Por Reingresar (Estado 6 - En Retorno)
             $query->where('estado_actual', 6);
        } elseif ($estado == 5) {
             // Histórico Temporal (Todo lo que salió como Temporal + Retornados 0)
             $query->where('tipo_retiro', 'Temporal')
                   ->where(function($q) {
                       $q->where('estado_actual', '>=', 2)
                         ->orWhere('estado_actual', 0);
                   })
                   ->orderBy('updated_at', 'desc');
        } elseif ($estado == 6) {
             // Histórico Definitivo
             $query->where('tipo_retiro', 'Definitivo')
                   ->where(function($q) {
                       $q->where('estado_actual', '>=', 2)
                         ->orWhere('estado_actual', 0);
                   })
                   ->orderBy('updated_at', 'desc');
        } else {
             // Por defecto mostrar pendientes (1)
             $query->where('estado_actual', 1);
        }

        $solicitudes = $query->orderBy('updated_at', 'desc')->get();

        return response()->json(['data' => $solicitudes]);
    }

    /**
     * Dispatch a request (Archive Action).
     */
    public function dispatchRequest(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:2,3', // 2=Temporal, 3=Definitivo
            'id_agencia_entrega' => 'required|exists:agencias,id',
        ]);

        $solicitud = SolicitudRetiro::find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $solicitud->estado_actual = $request->estado;
        $solicitud->id_usuario_despacho = Auth::id();
        $solicitud->fecha_envio = Carbon::now();
        $solicitud->id_agencia_entrega = $request->id_agencia_entrega;
        $solicitud->save();

        // Update document state for both system and registered historical documents
        if ($solicitud->id_documento) {
            $documento = \App\Models\Documento::find($solicitud->id_documento);
            if ($documento) {
                // estado: 2=Temporal -> 'temporal', 3=Definitivo -> 'definitivo'
                $documento->estado = ($request->estado == 3) ? 'definitivo' : 'temporal';
                $documento->save();
            }
        }

        return response()->json(['message' => 'Solicitud despachada correctamente', 'data' => $solicitud]);
    }

    /**
     * Register a physical document for a historical withdrawal request.
     */
    public function registerDocument(Request $request, $id)
    {
        $solicitud = SolicitudRetiro::findOrFail($id);

        if ($solicitud->id_expediente) {
            return response()->json(['message' => 'Esta solicitud ya pertenece a un expediente del sistema.'], 422);
        }

        $validated = $request->validate([
            'numero' => 'required',
            'fecha' => 'required|date',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'registro_propiedad_id' => 'nullable|exists:registro_propiedads,id',
            'propietario' => 'nullable|string',
            'autorizador' => 'nullable|string',
            'no_finca' => 'nullable|string',
            'folio' => 'nullable|string',
            'libro' => 'nullable|string',
            'no_dominio' => 'nullable|string',
            'referencia' => 'nullable|string',
            'monto_poliza' => 'nullable|numeric',
            'observacion' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create the document with the number provided by the user
            $documento = \App\Models\Documento::create($validated);

            // Update the Solicitud to point to this new document number
            // and save its ID for state tracking.
            $solicitud->numero_documento = $documento->numero;
            $solicitud->id_documento = $documento->id;
            $solicitud->save();

            DB::commit();

            return response()->json([
                'message' => 'Documento registrado exitosamente.',
                'data' => $documento
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar el documento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List requests SENT TO the user's agency (Incoming).
     */
    public function indexIncoming(Request $request)
    {
        $user = Auth::user();

        // Priorizar ID desde request o usar el del usuario
        $agencyId = $request->input('id_agencia') ?? $user->id_agencia ?? $user->getAgenciaId();

        if (!$agencyId) {
            return response()->json(['data' => []]);
        }

        // Buscar solicitudes donde la agencia de ENTREGA sea la del usuario
        // Y el estado sea > 1 (Enviado)
        $solicitudes = SolicitudRetiro::where('id_agencia_entrega', $agencyId)
            ->whereIn('estado_actual', [2, 3]) // Temporal o Definitivo
            ->with(['solicitante', 'despachador', 'entregador', 'agencia']) // Entregador puede ser null aun
            ->orderBy('fecha_envio', 'desc')
            ->paginate(10);

        return response()->json($solicitudes);
    }
    /**
     * Confirm physical receipt of the guarantee (Status -> 4).
     */
    public function confirmReceipt(Request $request, $id)
    {
        $solicitud = SolicitudRetiro::find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        // Validar que esté en estado "Enviado" (2 o 3)
        if (!in_array($solicitud->estado_actual, [2, 3])) {
            return response()->json(['message' => 'La solicitud no está en estado de envío válido para recepción.'], 422);
        }

        // Actualizar estado a 4 (Aceptado/Recibido)
        $solicitud->estado_actual = 4;

        // NOTA: Se solicitó NO registrar usuario de entrega aún, se hará en paso posterior.
        // $solicitud->id_usuario_entrega = Auth::id();

        $solicitud->save();

        return response()->json(['message' => 'Recepción confirmada correctamente', 'data' => $solicitud]);
    }
    /**
     * List requests pending delivery to associate (Status 4).
     * Includes requests created by the agency OR sent to the agency.
     */
    public function indexPendingDelivery(Request $request)
    {
        $user = Auth::user();

        // Priorizar ID desde request o usar el del usuario
        $agencyId = $request->input('id_agencia') ?? $user->id_agencia ?? $user->getAgenciaId();

        if (!$agencyId) {
            return response()->json(['data' => []]);
        }

        // Buscar solicitudes (Estado 4) donde la agencia sea ORIGEN o DESTINO
        // El usuario pidió ver SOLICITADOS por su agencia O ENVIADOS a su agencia.
        $solicitudes = \App\Models\SolicitudRetiro::where('estado_actual', 4)
            ->where(function($query) use ($agencyId) {
                $query->where('id_agencia_entrega', $agencyId)
                      ->orWhere('id_agencia', $agencyId);
            })
            ->with(['solicitante', 'agencia', 'agenciaEntrega'])
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        return response()->json($solicitudes);
    }

    /**
     * Mark request as Delivered to Associate (Status 5).
     * Uploads evidence file.
     */
    public function deliverToAssociate(Request $request, $id)
    {
        $request->validate([
            'evidencia' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        $solicitud = \App\Models\SolicitudRetiro::find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        // Validar Estado 4
        if ($solicitud->estado_actual != 4) {
            return response()->json(['message' => 'La solicitud no está en estado "Recibido" para entrega.'], 422);
        }

        try {
            if ($request->hasFile('evidencia')) {
                $file = $request->file('evidencia');
                $filename = 'entrega_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
                // Guardar en public/uploads/evidencia
                $file->move(public_path('uploads/evidencia'), $filename);

                $solicitud->evidencia_entrega_path = 'uploads/evidencia/' . $filename;
            }

            $solicitud->estado_actual = 5; // Entregado
            $solicitud->id_usuario_entrega = Auth::id(); // Usuario que hace la entrega
            // updated_at servirá como fecha de entrega
            $solicitud->save();

            return response()->json(['message' => 'Entrega finalizada correctamente', 'data' => $solicitud]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al subir evidencia: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List delivered requests (Status 5).
     */
    public function indexDelivered(Request $request)
    {
        $user = Auth::user();

        $agencyId = $request->input('id_agencia') ?? $user->id_agencia ?? $user->getAgenciaId();
        $role = $request->input('role'); // 'local_delivery' | 'external_delivery' | 'request'

        if (!$agencyId) {
             return response()->json(['data' => []]);
        }

        $query = \App\Models\SolicitudRetiro::where('estado_actual', 5)
            ->with(['solicitante', 'agencia', 'agenciaEntrega', 'entregador'])
            ->orderBy('updated_at', 'desc');

        if ($role === 'local_delivery') {
            // Creadas por MI agencia Y entregadas por MI agencia
            $query->where('id_agencia', $agencyId)
                  ->where('id_agencia_entrega', $agencyId);

        } else if ($role === 'external_delivery') {
             // Creadas por OTRAS agencias Y entregadas por MI agencia
             $query->where('id_agencia', '!=', $agencyId)
                   ->where('id_agencia_entrega', $agencyId);

        } else if ($role === 'delivery') {
            // General: entregadas por MI agencia (sin importar origen) - Mantenido por compatibilidad
            $query->where('id_agencia_entrega', $agencyId);

        } else if ($role === 'request') {
             // Solicitadas por MI agencia (sin importar quien entregó)
             $query->where('id_agencia', $agencyId);

        } else {
             // Fallback: ambas
             $query->where(function($q) use ($agencyId) {
                 $q->where('id_agencia_entrega', $agencyId)
                   ->orWhere('id_agencia', $agencyId);
             });
        }

        $solicitudes = $query->paginate(10);

        return response()->json($solicitudes);
    }

    /**
     * Return a Temporal request to Archive (Status 0).
     */
    public function returnToArchive(Request $request, $id)
    {
        $solicitud = \App\Models\SolicitudRetiro::find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        // Solo permitir si es Temporal
        if ($solicitud->tipo_retiro !== 'Temporal') {
            return response()->json(['message' => 'Solo las garantías con retiro Temporal pueden ser reingresadas al archivo.'], 422);
        }

        // Validar Estado 4 (Recibido en Agencia)
        if ($solicitud->estado_actual != 4) {
            return response()->json(['message' => 'La garantía debe ser recibida en agencia antes de devolverla.'], 422);
        }

        // Actualizar estado a 6 (En Retorno)
        $solicitud->estado_actual = 6;
        $solicitud->fecha_retorno = Carbon::now();
        $solicitud->id_usuario_retorno = Auth::id();
        $solicitud->observacion_retorno = $request->input('observacion');
        $solicitud->save();

        return response()->json(['message' => 'Solicitud de devolución enviada correctamente.', 'data' => $solicitud]);
    }

    /**
     * Confirm return to Archive (Status 6 -> 0).
     */
    public function confirmReturn(Request $request, $id)
    {
        $solicitud = \App\Models\SolicitudRetiro::find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        if ($solicitud->estado_actual != 6) {
             return response()->json(['message' => 'La solicitud no está en estado de retorno.'], 422);
        }

        $solicitud->estado_actual = 0; // Archivado / Finalizado
        $solicitud->fecha_confirmacion_retorno = Carbon::now();
        $solicitud->id_usuario_confirmacion_retorno = Auth::id();
        $solicitud->save();

        // Update document state back to active since it returned
        if ($solicitud->id_documento) {
            $documento = \App\Models\Documento::find($solicitud->id_documento);
            if ($documento) {
                $documento->estado = 'activo';
                $documento->save();
            }
        }

        return response()->json(['message' => 'Retorno confirmado. Garantía archivada nuevamente.', 'data' => $solicitud]);
    }

    /**
     * Delete a request (Super Admin only).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $roles = $user->roles_list ?? [];

        if (!in_array('Super Admin', $roles)) {
            return response()->json(['message' => 'No autorizado. Se requiere rol de Super Admin.'], 403);
        }

        $solicitud = \App\Models\SolicitudRetiro::findOrFail($id);

        if ($solicitud->estado_actual != 1) {
            return response()->json(['message' => 'Solo se pueden eliminar solicitudes en estado pendiente.'], 400);
        }

        $solicitud->delete();

        return response()->json(['message' => 'Solicitud eliminada correctamente.']);
    }
}
