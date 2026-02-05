<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use App\Models\SeguimientoFecha;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SeguimientoController extends Controller
{
    /**
     * Enviar expediente a secretaría (Estado 1).
     */
    public function enviarASecretaria(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente'
        ]);

        $codigo = $request->codigo_cliente;

        try {
            DB::beginTransaction();

            // 1. Actualizar o Crear registro en seguimiento_expedientes
            // 1. Actualizar o Crear registro en seguimiento_expedientes
            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $codigo]);

            // Update only state-related fields without wiping others
            $seguimiento->id_estado = 1; // 1: Enviado a Secretaria
            $seguimiento->enviado_a_archivos = false;

            // Preserve existing observations unless explicitly changing
            // We do NOT clear observacion_envio or observacion_rechazo here to keep history as requested.
            // Only if we wanted to enforce a "clean slate" would we null them, but user requested otherwise.

            $seguimiento->save();

            // 2. Actualizar o Crear cronología en seguimiento_fechas
            // Use updateOrCreate since it's 1:1
            SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $codigo],
                ['f_enviado_secretaria' => Carbon::now()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente enviado a secretaría correctamente.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar expediente: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Obtener expedientes en el buzón de Secretaría.
     * Filtra por el ÚLTIMO estado registrado.
     * Default: 1 (Enviado a Secretaria).
     * Puede usarse para 2 (Rechazado/Regresado) también.
     */
    public function buzonSecretaria(Request $request)
    {
        $estado = $request->query('status', 1);

        // Obtener expedientes cuyo estado actual sea $estado
        // Al ser 1:1 ahora, la subquery es más simple o se puede usar whereHas
        $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) use ($estado) {
            $query->where('id_estado', $estado);
        })
        ->with('fechas') // Eager load fechas
        ->orderBy('fecha_inicio', 'desc')
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Rechazar expediente y regresar a asesores (Estado 2).
     */
    public function rechazarExpediente(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
            'observacion' => 'required|string|max:1000'
        ]);

        $codigo = $request->codigo_cliente;
        $observacion = $request->observacion;

        try {
            DB::beginTransaction();

            // 1. Actualizar registro en seguimiento_expedientes
            // 1. Actualizar registro en seguimiento_expedientes
            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $codigo]);

            $seguimiento->id_estado = 2; // 2: Rechazado / Regresado a Asesores
            $seguimiento->enviado_a_archivos = false;
            $seguimiento->observacion_rechazo = $observacion; // Update rejection reason

            // Do NOT wipe observacion_envio

            $seguimiento->save();

            // 2. Actualizar fecha de retorno en seguimiento_fechas
            SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $codigo],
                ['f_retorno_asesores' => Carbon::now()]
            );

            // TODO: Podríamos disparar notificaciones aquí.

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente regresado a asesores correctamente.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar expediente: ' . $e->getMessage()
            ], 500);
        }
    }
}
