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

        if ($solicitud->estado_solicitud !== 'recibido_por_admin') {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud debe ser aceptada primero antes de poder despacharla.'
            ], 400);
        }

        // Agregar observaciones si vienen en el request
        $observaciones = $solicitud->observacion_despacho;
        if ($request->filled('observacion_despacho')) {
            $observaciones .= "\n[Despacho]: " . $request->observacion_despacho;
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
        $estado = $request->query('estado', 'pendientes'); // pendientes, despachados, historico

        $query = SolicitudAdministrativa::with(['expediente', 'usuarioSolicita', 'agencia', 'usuarioDespacho']);

        if ($estado === 'pendientes') {
            // Mostrar las que están en 'pendiente' o 'recibido_por_admin'
            $query->whereIn('estado_solicitud', ['pendiente', 'recibido_por_admin']);
        } elseif ($estado === 'despachados') {
            // Mostrar las que el admin ya despachó y están esperando confirmación o reingreso
            $query->whereNotIn('estado_solicitud', ['pendiente', 'recibido_por_admin', 'archivado']);
        } elseif ($estado === 'historico') {
            // Mostrar las archivadas/finalizadas
            $query->where('estado_solicitud', 'archivado')
                  ->orWhere('estado', 'archivado');
        }

        $solicitudes = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $solicitudes
        ]);
    }
}
