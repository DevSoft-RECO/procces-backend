<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;

class AbogadoController extends Controller
{
    /**
     * List expedientes in "En manos de abogado" (State 8).
     */
    public function buzon(Request $request)
    {
        // Fetch expedientes where the *latest* tracking status is 8
        $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->where('id_estado', 8)
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
}
