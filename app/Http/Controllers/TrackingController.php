<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;

class TrackingController extends Controller
{
    /**
     * Obtener el historial de seguimiento de un expediente.
     */
    public function getHistory($codigoCliente)
    {
        // Verificar existencia del expediente
        $expediente = NuevoExpediente::find($codigoCliente);

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado'
            ], 404);
        }

        // Obtener seguimientos con estados y usuarios
        // Asumiendo que 'usuario' es un campo string en seguimiento, si es relación agregarla aquí
        $seguimientos = SeguimientoExpediente::where('id_expediente', $codigoCliente)
            ->with(['estado', 'estadoSecundario'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'expediente' => $expediente, // Basic info header
                'seguimientos' => $seguimientos
            ]
        ]);
    }
}
