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

            // 1. Crear registro en seguimiento_expedientes
            $seguimiento = SeguimientoExpediente::create([
                'id_expediente' => $codigo,
                'id_estado' => 1, // 1: Enviado a Secretaria
                'enviado_a_archivos' => false,
                'observacion_envio' => 'Expediente enviado inicialmente a secretaría.',
                'observacion_rechazo' => null
            ]);

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
}
