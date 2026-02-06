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
            $seguimiento->enviado_a_archivos = 'No';

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
            $seguimiento->enviado_a_archivos = 'No';
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
    /**
     * Aceptar expediente (Estado 3).
     * Esto habilita la opción de enviar a archivo.
     */
    public function aceptarExpediente(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente'
        ]);

        $codigo = $request->codigo_cliente;

        try {
            DB::beginTransaction();

            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $codigo]);

            $seguimiento->id_estado = 3; // 3: Aceptado / En Revisión Final (Previo a Archivo)
            // No tocamos enviado_a_archivos aquí, eso es en el siguiente paso.
            $seguimiento->save();

            // Actualizar fecha de aceptación
            SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $codigo],
                ['f_aceptado_secretaria' => Carbon::now()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente aceptado correctamente.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aceptar expediente: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Enviar a Archivo (Estado 1 -> 4 si es garantía real, o solo marcar enviado).
     */
    public function enviarArchivo(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
            'tiene_garantia_real' => 'required|boolean', // Enviado desde frontend checkbox/option
            'observacion' => 'required|string|max:1000'
        ]);

        $codigo = $request->codigo_cliente;
        // La lógica de "envio a archivos" traducida:
        // Check "Tiene Garantía Real" (Si/No) -> mapped to enviado_a_archivos ('Si'/'No')
        // Si CHECKED (Si): Guardar 'Si', Observación, y estado secundario = 4.
        // Si UNCHECKED (No): Guardar 'No', Observación. (Y ahí muere, no cambio estado secundario).

        $enviadoAArchivos = $request->tiene_garantia_real ? 'Si' : 'No';

        try {
            DB::beginTransaction();

            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $codigo]);

            $seguimiento->enviado_a_archivos = $enviadoAArchivos;
            $seguimiento->observacion_envio = $request->observacion;

            if ($request->tiene_garantia_real) {
                $seguimiento->id_estado_secundario = 4; // Register secondary state 4
                // Podríamos cambiar el estado principal si el flujo lo requiere, pero el usuario dijo "se registra el estado 4 en la columna id_estado_adicional"
            } else {
                 // Usuario dijo "ahí muere esa acción".
                 // Mantenemos estado actual (3) o limpiamos secundario?
                 // Asumiremos que si no es garantía real, no entra al flujo paralelo (secundario NULL o sin cambios).
                 // Forzaremos NULL por consistencia si cambia de opinión:
                 $seguimiento->id_estado_secundario = null;
            }

            $seguimiento->save();

            // Actualizar fecha de envío a archivo (solo si se marca garantía real/envío físico)
             if ($request->tiene_garantia_real) {
                SeguimientoFecha::updateOrCreate(
                    ['id_expediente' => $codigo],
                    ['f_enviado_archivos' => Carbon::now()]
                );
             }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Información de archivo actualizada.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
             return response()->json([
                'success' => false,
                'message' => 'Error al enviar a archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar a Protocolo (Estado 3 -> 5).
     */
    public function enviarProtocolo(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente'
        ]);

        $codigo = $request->codigo_cliente;

        try {
            DB::beginTransaction();

            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $codigo]);

            $seguimiento->id_estado = 5; // 5: Enviar a Protocolo
            // Mantenemos otros campos igual
            $seguimiento->save();

            // Actualizar fecha protocolo
             SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $codigo],
                ['f_enviado_protocolos' => Carbon::now()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente enviado a protocolo correctamente.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
             return response()->json([
                'success' => false,
                'message' => 'Error al enviar a protocolo: ' . $e->getMessage()
            ], 500);
        }
    }
}
