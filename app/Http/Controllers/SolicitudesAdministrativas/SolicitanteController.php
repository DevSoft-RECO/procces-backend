<?php

namespace App\Http\Controllers\SolicitudesAdministrativas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;

class SolicitanteController extends Controller
{
    /**
     * Busca un expediente para solicitar su retiro.
     * Criterio: ID del Expediente OR Número de Documento
     * Regla: Debe existir en seguimiento_expedientes con archivo_administrativo = 'si'
     */
    public function buscarExpediente(Request $request)
    {
        $request->validate([
            'criterio' => 'required|string',
        ]);

        $criterio = $request->criterio;

        // 1. Buscar por ID (llave primaria) o Número de Documento (código único)
        $expediente = NuevoExpediente::with(['agencia'])
            ->where(function($query) use ($criterio) {
                $query->where('id', $criterio)
                      ->orWhere('numero_documento', $criterio);
            })
            ->first();

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró ningún expediente con el ID o Número de Documento proporcionado.',
            ], 404);
        }

        // 2. Regla de Validación (La "Llave de Paso")
        $seguimiento = SeguimientoExpediente::where('id_expediente', $expediente->id)
            ->where('archivo_administrativo', 'si')
            ->first();

        if (!$seguimiento) {
            return response()->json([
                'success' => false,
                'message' => 'El expediente no se encuentra en el Archivo Administrativo. No es posible generar la solicitud de retiro.',
            ], 403);
        }

        // 3. (Opcional) Validar si ya existe una solicitud activa para no duplicar
        $solicitudActiva = \App\Models\SolicitudAdministrativa::where('id_expediente', $expediente->id)
            ->whereNotIn('estado', ['finalizado', 'devuelto']) // Ajustar según los estados finales
            ->first();

        if ($solicitudActiva) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una solicitud en proceso para este expediente.',
                'solicitud' => $solicitudActiva
            ], 400);
        }

        return response()->json([
            'success' => true,
            'expediente' => $expediente,
            'message' => 'Expediente validado correctamente y listo para solicitud de retiro.'
        ]);
    }

    /**
     * Registra el inicio de un retiro de archivo.
     */
    public function crearSolicitud(Request $request)
    {
        $request->validate([
            'id_expediente' => 'required|exists:nuevos_expedientes,id',
            'observaciones' => 'required|string',
            'id_agencia' => 'required|exists:agencias,id', // Se recibe del frontend o se puede sacar de auth()->user()
        ]);

        // Verificar si ya existe una solicitud activa
        $solicitudActiva = \App\Models\SolicitudAdministrativa::where('id_expediente', $request->id_expediente)
            ->whereNotIn('estado', ['finalizado', 'devuelto'])
            ->first();

        if ($solicitudActiva) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una solicitud en proceso para este expediente.',
            ], 400);
        }

        try {
            $solicitud = \App\Models\SolicitudAdministrativa::create([
                'id_expediente' => $request->id_expediente,
                'id_usuario_solicita' => auth()->id(),
                'id_agencia' => $request->id_agencia, // Usamos la que manda el front (como hacen en otras partes del app) o auth->user->agencia_id
                'fecha_solicitud' => now(),
                'estado' => 'pendiente',
                'observaciones' => $request->observaciones,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de retiro creada exitosamente.',
                'solicitud' => $solicitud
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las solicitudes activas (en proceso) de la agencia del usuario.
     * Cualquier estado distinto a 'archivado'
     */
    public function index(Request $request)
    {
        $agenciaId = $request->query('id_agencia') ?? auth()->user()->id_agencia ?? auth()->user()->agencia_id;

        $solicitudes = \App\Models\SolicitudAdministrativa::with(['expediente', 'usuarioSolicita'])
            ->where('id_agencia', $agenciaId)
            ->where('estado', '!=', 'archivado') // O el estado final que definas
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $solicitudes
        ]);
    }

    /**
     * Obtiene el historial de solicitudes finalizadas (archivadas) de la agencia del usuario.
     */
    public function historico(Request $request)
    {
        $agenciaId = $request->query('id_agencia') ?? auth()->user()->id_agencia ?? auth()->user()->agencia_id;

        $solicitudes = \App\Models\SolicitudAdministrativa::with(['expediente', 'usuarioSolicita'])
            ->where('id_agencia', $agenciaId)
            ->where('estado', 'archivado')
            ->orderBy('fecha_finalizacion', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $solicitudes
        ]);
    }

    /**
     * El solicitante (agencia) confirma que recibió el expediente físico.
     */
    public function confirmarRecepcion($id)
    {
        $solicitud = \App\Models\SolicitudAdministrativa::findOrFail($id);

        $userAgenciaId = auth()->user()->id_agencia ?? auth()->user()->agencia_id;
        if ($userAgenciaId && $solicitud->id_agencia != $userAgenciaId) {
            return response()->json(['success' => false, 'message' => 'No Autorizado: El expediente pertenece a otra agencia.'], 403);
        }

        if ($solicitud->estado_solicitud !== 'despachado' || $solicitud->confirmacion_solicitante === 'si') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede confirmar la recepción en este estado.'
            ], 400);
        }

        $solicitud->update([
            'confirmacion_solicitante' => 'si',
            'estado' => 'en_agencia', // Marcamos que el file está físicamente en la agencia
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recepción confirmada exitosamente.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * El solicitante (agencia) marca el expediente para devolverlo al archivo central.
     */
    public function iniciarDevolucion($id)
    {
        $solicitud = \App\Models\SolicitudAdministrativa::findOrFail($id);

        $userAgenciaId = auth()->user()->id_agencia ?? auth()->user()->agencia_id;
        if ($userAgenciaId && $solicitud->id_agencia != $userAgenciaId) {
            return response()->json(['success' => false, 'message' => 'No Autorizado: El expediente pertenece a otra agencia.'], 403);
        }

        if ($solicitud->confirmacion_solicitante !== 'si' || $solicitud->fecha_devolucion_iniciada !== null) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible iniciar la devolución de este expediente.'
            ], 400);
        }

        $solicitud->update([
            'fecha_devolucion_iniciada' => now(),
            'estado' => 'retornando', // Marcador visual de que está volviendo al archivo
            // confirmacion_reingreso sigue en 'pendiente' hasta que el admin central lo reciba
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expediente marcado para devolución.',
            'solicitud' => $solicitud
        ]);
    }
}
