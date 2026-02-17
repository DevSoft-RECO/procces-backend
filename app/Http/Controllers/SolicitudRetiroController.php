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
             // Si no existe el expediente, no hay nada que pedir (Regla estricta solicitada)
             // Aunque el frontend maneja "found: false" para manual, el usuario pidió corregir lógica base.
             // Mantendremos la respuesta de "no encontrado" pero el frontend decidirá si muestra manual o no.
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún expediente con ese número de documento.',
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

        // 3. CASO 1: Validar reglas de negocio con los DOCUMENTOS asociados (Relación Eloquent)
        // Verificar si algún documento está compartido con OTRO expediente ACTIVO
        if ($expediente->documentos->isNotEmpty()) {
            foreach ($expediente->documentos as $doc) {
                // Verificar si este documento está vinculado a OTROS expedientes que estén ACTIVOS
                $otrosActivos = $doc->nuevosExpedientes()
                    ->where('nuevos_expedientes.id', '!=', $expediente->id) // Diferente al actual
                    ->where('nuevos_expedientes.estado', 'activo') // Estado de bloqueo
                    ->get();

                if ($otrosActivos->count() > 0) {
                     $info = $otrosActivos->map(fn($e) => $e->numero_documento)->join(', ');
                     return response()->json([
                        'error' => true,
                        // Mensaje Caso 1
                        'message' => "No se puede retirar porque la garantía No.({$doc->numero}) está amarrada a otro expediente ({$info}) que aún está activo. Soliciten su revisión para verificar si está activo o solo no le han dado de baja.",
                        'bloqueado' => true
                    ]);
                }
            }
        }

        // 4. CASO 2: Validar estado del expediente ACTUAL
        // "si el docuemnto esta amarrado a otro expediente pero esta ya no esta activo pero el actual sigue activo"
        // (Ya pasamos la validación de "otro activo", así que si llegamos aquí, los otros están inactivos o no existen)
        if ($expediente->estado == 'activo') {
             return response()->json([
                'error' => true,
                // Mensaje Caso 2
                'message' => 'Este expediente aún se encuentra activo. Si el crédito ya ha sido cancelado, solicite su baja en archivo para poder proceder con la liberación de la solicitud.',
                'bloqueado' => true
            ]);
        }

        // 5. CASO 4: Liberación
        // Si ninguna de estos casos se cumple, se libera la solicitud (con datos precargados)
        $docPrincipal = $expediente->documentos->first();

        return response()->json([
            'found' => true,
            'data' => [
                'numero_documento' => $expediente->numero_documento,
                'titulo_nombre' => $expediente->nombre_asociado,
                'id_expediente' => $expediente->id,
                'es_manual' => false,
                'documento_info' => $docPrincipal,
                'documentos' => $expediente->documentos
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
        if (!$request->es_manual) {
            $documento = Documento::where('numero', $request->numero_documento)->first();

            if ($documento) {
                 $tieneActivos = $documento->nuevosExpedientes()
                    ->where('estado', '!=', 'Cancelado')
                    ->exists();
                 if ($tieneActivos) {
                     return response()->json(['message' => 'El documento está asociado a un expediente activo.'], 422);
                 }
            } else {
                // Fallback validation: Check NuevoExpediente directly
                $expedienteDirecto = NuevoExpediente::where('numero_documento', $request->numero_documento)->first();
                if ($expedienteDirecto && $expedienteDirecto->estado != 'Cancelado') {
                    return response()->json(['message' => "El documento está amarrado a un expediente activo: {$expedienteDirecto->numero_documento} ({$expedienteDirecto->estado})."], 422);
                }
            }
        }

        try {
            DB::beginTransaction();

            $solicitud = SolicitudRetiro::create([
                'id_expediente' => $request->id_expediente,
                'numero_documento' => $request->numero_documento,
                'titulo_nombre' => $request->titulo_nombre,
                'es_manual' => $request->es_manual ?? false,
                'id_agencia' => $user->id_agencia, // Asumiendo que el usuario tiene id_agencia
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

        // Si el usuario no tiene agencia, retornar error o vacío
        if (!$user->id_agencia) {
             return response()->json(['data' => []]);
        }

        $solicitudes = SolicitudRetiro::where('id_agencia', $user->id_agencia)
            ->with(['solicitante', 'despachador'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $solicitudes]);
    }

    /**
     * List requests for the Archive Mailbox (Buzón).
     */
    public function indexArchive(Request $request)
    {
        // Filtros opcionales: estado
        $estado = $request->input('estado');

        $query = SolicitudRetiro::with(['agencia', 'solicitante']);

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
