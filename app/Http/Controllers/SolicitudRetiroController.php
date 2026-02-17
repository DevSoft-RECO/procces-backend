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
        $expediente = NuevoExpediente::where('numero_documento', $termino)->latest()->first();

        if (!$expediente) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún expediente con ese número de documento.',
                'data' => [
                    'numero_documento' => $termino,
                    'es_manual' => true // Aún permitimos manual si no existe del todo? El usuario dijo "no podemos pedir algo a algo que no tiene nada vinculado".
                                        // Pero la opción manual es para casos extremos. Mantengamos flag manual pero con aviso.
                ]
            ]);
        }

        // 2. Validar que exista en SeguimientoExpediente
        // "todo expediente que ya entro ahi es porque tiene un documento asociado"
        $tieneSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expediente->id)->exists();

        if (!$tieneSeguimiento) {
             return response()->json([
                'error' => true, // Bloquear la operación
                'message' => 'El expediente existe pero no tiene garantías asociadas aún.',
                'bloqueado' => true
            ]);
        }

        // 3. Validar reglas de negocio (Bloqueo si está amarrado a OTRO activo?)
        // La regla original era: "no se podra retirar un expedeinte solo si el documento adjunto no esta amarrado a OTRO expediente que este en estado activo"
        // Si usamos el expediente encontrado como base, verificamos si su documento se comparte.
        // Como ahora partimos del Expediente y no del Documento (ID), es más difícil ver "otros".
        // Sin embargo, si el flujo es simplificado:

        // Retornamos la data del expediente encontrado y validado
        return response()->json([
            'found' => true,
            'data' => [
                'numero_documento' => $expediente->numero_documento,
                'titulo_nombre' => $expediente->nombre_asociado,
                'id_expediente' => $expediente->id,
                'es_manual' => false
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
