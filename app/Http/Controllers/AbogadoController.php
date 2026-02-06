<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;

class AbogadoController extends Controller
{
    /**
     * List expedientes in "En manos de abogado" (State 8) or "Recibido por abogado" (State 9).
     */
    public function buzon(Request $request)
    {
        // Fetch expedientes where the *latest* tracking status is 8 or 9
        $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->whereIn('id_estado', [8, 9])
                  ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.codigo_cliente)');
        })
        ->with(['seguimientos' => function ($query) {
            $query->orderBy('created_at', 'desc')->with(['estado', 'bufete.user', 'bufete.agencia']);
        }, 'fechas'])
        ->get();

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Mark expedientes as received by the lawyer.
     * Updates existing record (state 8 -> 9) and sets timestamp.
     */
    public function recibir(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
        ]);

        $codigoCliente = $request->codigo_cliente;

        // 1. Update Tracking State (seguimiento_expedientes)
        // Find the latest tracking record (which should be state 8)
        $ultimoSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $codigoCliente)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($ultimoSeguimiento && $ultimoSeguimiento->id_estado == 8) {
            $ultimoSeguimiento->id_estado = 9; // Change to state 9 (Recibido/Revisión)
            $ultimoSeguimiento->save();
        }

        // 2. Update Dates (seguimiento_fechas)
        // Find or create the dates record
        $fechas = \App\Models\SeguimientoFecha::firstOrCreate(
            ['id_expediente' => $codigoCliente]
        );

        // Update the accepted date if not already set
        if (!$fechas->f_aceptado_abogado) {
            $fechas->f_aceptado_abogado = now();
            $fechas->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Expediente marcado como recibido correctamente.',
            'data' => $fechas
        ]);
    }
}
