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
                  ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.id)');
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
            'id' => 'required|exists:nuevos_expedientes,id',
        ]);

        $id = $request->id;

        // 1. Update Tracking State (seguimiento_expedientes)
        // Find the latest tracking record (which should be state 8)
        $ultimoSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($ultimoSeguimiento && $ultimoSeguimiento->id_estado == 8) {
            $ultimoSeguimiento->id_estado = 9; // Change to state 9 (Recibido/Revisión)
            $ultimoSeguimiento->save();
        }

        // 2. Update Dates (seguimiento_fechas)
        // Find or create the dates record
        $fechas = \App\Models\SeguimientoFecha::firstOrCreate(
            ['id_expediente' => $id]
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

    /**
     * Send expediente back to Secretaria de Creditos (State 10).
     */
    public function enviarSecretaria(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nuevos_expedientes,id',
        ]);

        $id = $request->id;

        // 1. Update Tracking State (seguimiento_expedientes)
        $ultimoSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($ultimoSeguimiento) {
            $ultimoSeguimiento->id_estado = 10; // Devolución a Secretaría
            $ultimoSeguimiento->save();
        }

        // 2. Update Dates (seguimiento_fechas)
        $fechas = \App\Models\SeguimientoFecha::firstOrCreate(
            ['id_expediente' => $id]
        );

        if (!$fechas->f_enviado_secretaria_credito) {
            $fechas->f_enviado_secretaria_credito = now();
            $fechas->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Expediente enviado a Secretaría de Créditos correctamente.',
        ]);
    }

    /**
     * List expedientes returned to Secretaria (State 10).
     */

public function devueltos(Request $request)
{
    $user = $request->user();
    $isSuperAdmin = $user->hasRole('Super Admin');

    // Iniciamos la consulta base con los campos optimizados
    $query = NuevoExpediente::select([
            'id',
            'codigo_cliente',
            'nombre_asociado',
            'numero_documento'
        ])
        ->with(['fechas:id_expediente,f_enviado_secretaria_credito']);

    // APLICACIÓN DE RESTRICCIONES
    if (!$isSuperAdmin) {
        // Si NO es admin, solo ve expedientes donde ÉL (su bufete)
        // haya tenido participación en los seguimientos
        $query->whereHas('seguimientos', function ($q) use ($user) {
            $bufeteId = \App\Models\Bufete::where('user_id', $user->id)->value('id');
            $q->where('bufete_id', $bufeteId);
        });
    } else {
        // Si ES Super Admin, solo nos aseguramos de que el expediente
        // tenga al menos un seguimiento (para que sea un expediente con historial)
        $query->has('seguimientos');
    }

    $expedientes = $query->latest()->paginate(15);

    return response()->json($expedientes);
}

}
