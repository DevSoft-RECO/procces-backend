<?php

namespace App\Http\Controllers\SolicitudesAdministrativas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SolicitudAdministrativa;

class DespachoController extends Controller
{
    /**
     * Cambiar el estado de la solicitud a 'recibido_por_admin'
     */
    public function aceptarSolicitud($id)
    {
        $solicitud = SolicitudAdministrativa::findOrFail($id);

        $user = auth()->user();
        $isSuperAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) || in_array('Super Admin', (array)($user->roles_list ?? [])));
        $userAgenciaId = $user ? (method_exists($user, 'getAgenciaId') ? $user->getAgenciaId() : $user->id_agencia) : null;

        if (!$isSuperAdmin && $userAgenciaId && $solicitud->id_agencia && $solicitud->id_agencia != $userAgenciaId) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene autorización para operar solicitudes de otra agencia.'
            ], 403);
        }

        if ($solicitud->estado_solicitud !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no se encuentra en estado pendiente.'
            ], 400);
        }

        $solicitud->update([
            'estado_solicitud' => 'recibido_por_admin'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud aceptada y en proceso.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * Registra la salida física del expediente hacia la agencia.
     */
    public function despacharExpediente(Request $request, $id)
    {
        $request->validate([
            'observacion_despacho' => 'nullable|string'
        ]);

        $solicitud = SolicitudAdministrativa::findOrFail($id);

        $user = auth()->user();
        $isSuperAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) || in_array('Super Admin', (array)($user->roles_list ?? [])));
        $userAgenciaId = $user ? (method_exists($user, 'getAgenciaId') ? $user->getAgenciaId() : $user->id_agencia) : null;

        if (!$isSuperAdmin && $userAgenciaId && $solicitud->id_agencia && $solicitud->id_agencia != $userAgenciaId) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene autorización para operar solicitudes de otra agencia.'
            ], 403);
        }

        if ($solicitud->estado_solicitud !== 'recibido_por_admin') {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud debe ser aceptada primero antes de poder despacharla.'
            ], 400);
        }

        // Agregar observaciones si vienen en el request
        $observaciones = $solicitud->observacion_despacho;
        if ($request->filled('observacion_despacho')) {
            $observaciones = $request->observacion_despacho;
        }

        $solicitud->update([
            'estado_solicitud' => 'despachado',
            'id_usuario_despacho' => auth()->id(),
            'fecha_despacho' => now(),
            'confirmacion_solicitante' => 'pendiente',
            'observacion_despacho' => $observaciones
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expediente despachado correctamente.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * Obtiene todas las solicitudes de retiro administrativo para la vista del administrador.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $roles = $user ? ($user->roles_list ?? []) : [];
        $isSuperAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) || in_array('Super Admin', (array)$roles));

        $estado = $request->query('estado', 'pendientes'); // pendientes, despachados, historico

        $query = SolicitudAdministrativa::with(['expediente', 'usuarioSolicita', 'agencia', 'usuarioDespacho']);

        // 1. Filtro por Estado (Pestañas)
        if ($estado === 'pendientes') {
            // Mostrar las que están en 'pendiente' o 'recibido_por_admin'
            $query->whereIn('estado_solicitud', ['pendiente', 'recibido_por_admin']);
        } elseif ($estado === 'despachados') {
            // Mostrar las que el admin ya despachó y están esperando confirmación o reingreso
            $query->whereNotIn('estado_solicitud', ['pendiente', 'recibido_por_admin', 'archivado']);
        } elseif ($estado === 'historico') {
            // Mostrar las archivadas/finalizadas
            $query->where(function($q) {
                $q->where('estado_solicitud', 'archivado')
                  ->orWhere('estado', 'archivado');
            });
        }

        // 2. Filtro de Seguridad por Agencia:
        // Si NO es Super Admin, SOLO ve solicitudes que pertenezcan a su agencia
        if (!$isSuperAdmin) {
            $userAgenciaId = $user ? (method_exists($user, 'getAgenciaId') ? $user->getAgenciaId() : $user->id_agencia) : null;
            if ($userAgenciaId) {
                $query->where(function($q) use ($userAgenciaId) {
                    $q->where('id_agencia', $userAgenciaId)
                      ->orWhereHas('expediente', function($eq) use ($userAgenciaId) {
                          $eq->where('id_agencia', $userAgenciaId);
                      });
                });
            } else {
                // Usuario sin agencia asignada y no Super Admin no ve registros ajenos
                $query->whereRaw('1 = 0');
            }
        } else {
            // Para Super Admin: es global, pero si envió un id_agencia específico se aplica
            if ($request->filled('id_agencia')) {
                $filtroAgencia = $request->input('id_agencia');
                $query->where(function($q) use ($filtroAgencia) {
                    $q->where('id_agencia', $filtroAgencia)
                      ->orWhereHas('expediente', function($eq) use ($filtroAgencia) {
                          $eq->where('id_agencia', $filtroAgencia);
                      });
                });
            }
        }

        // 3. Buscador por ID (solicitud o expediente) y numero_documento
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $cleanNumeric = ltrim($search, '#');

            $query->where(function($q) use ($search, $cleanNumeric) {
                // Buscar por ID de solicitud
                if (is_numeric($cleanNumeric)) {
                    $q->where('id', $cleanNumeric);
                }
                // O buscar en el expediente por ID o numero_documento
                $q->orWhereHas('expediente', function($eq) use ($search, $cleanNumeric) {
                    $eq->where('numero_documento', 'like', "%{$search}%");
                    if (is_numeric($cleanNumeric)) {
                        $eq->orWhere('id', $cleanNumeric);
                    }
                });
            });
        }

        $solicitudes = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $solicitudes,
            'is_super_admin' => $isSuperAdmin
        ]);
    }

    /**
     * El administrador central confirma que el documento retornó físicamente
     * al archivo y finaliza el proceso administrativo de este retiro.
     */
    public function confirmarReingreso($id)
    {
        $solicitud = SolicitudAdministrativa::findOrFail($id);

        $user = auth()->user();
        $isSuperAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) || in_array('Super Admin', (array)($user->roles_list ?? [])));
        $userAgenciaId = $user ? (method_exists($user, 'getAgenciaId') ? $user->getAgenciaId() : $user->id_agencia) : null;

        if (!$isSuperAdmin && $userAgenciaId && $solicitud->id_agencia && $solicitud->id_agencia != $userAgenciaId) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene autorización para operar solicitudes de otra agencia.'
            ], 403);
        }

        if ($solicitud->fecha_devolucion_iniciada === null || $solicitud->confirmacion_reingreso === 'si') {
            return response()->json([
                'success' => false,
                'message' => 'El expediente aún no ha sido marcado para devolución por la agencia o ya fue reingresado.'
            ], 400);
        }

        $solicitud->update([
            'confirmacion_reingreso' => 'si',
            'fecha_finalizacion' => now(),
            'estado_solicitud' => 'archivado', // Estado global de la solicitud finalizado
            'estado' => 'archivado' // Marcador para la vista
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expediente reingresado y solicitud finalizada correctamente.',
            'solicitud' => $solicitud
        ]);
    }
}
