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
        $expediente = NuevoExpediente::with(['agencia', 'asesor'])
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
}
