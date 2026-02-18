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

            $solicitud = SolicitudRetiro::create([
                'id_expediente' => $request->id_expediente,
                'numero_documento' => $request->numero_documento,
                'titulo_nombre' => $request->titulo_nombre,
                'es_manual' => $request->es_manual ?? false,
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
     * List requests for the Agency History.
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
            ->with(['solicitante', 'despachador'])
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Paginación de 10 elementos

        return response()->json($solicitudes);
    }

    /**
     * List requests for the Archive Mailbox (Buzón).
     */
    public function indexArchive(Request $request)
    {
        // Filtros opcionales: estado
        $estado = $request->input('estado');

        $query = SolicitudRetiro::with(['agencia', 'solicitante', 'documento.tipoDocumento', 'documento.registroPropiedad', 'expedienteHistorico']);

        if ($estado !== null) {
            $query->where('estado_actual', $estado);
        } else {
             // Por defecto mostrar pendientes (1)
             $query->where('estado_actual', 1);
        }

        $solicitudes = $query->orderBy('fecha_solicitud', 'asc')->get();

        return response()->json(['data' => $solicitudes]);
    }

    /**
     * Dispatch a request (Archive Action).
     */
    public function dispatchRequest(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:2,3', // 2=Temporal, 3=Definitivo
        ]);

        $solicitud = SolicitudRetiro::find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $solicitud->estado_actual = $request->estado;
        $solicitud->id_usuario_despacho = Auth::id();
        $solicitud->fecha_envio = Carbon::now();
        $solicitud->save();

        return response()->json(['message' => 'Solicitud despachada correctamente', 'data' => $solicitud]);
    }
}
