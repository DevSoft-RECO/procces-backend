<?php

namespace App\Http\Controllers;

use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecretariaAgenciaController extends Controller
{
    /**
     * Adjuntar número de contrato al expediente en estado 3.
     */
    public function adjuntarContrato(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
            'numero_contrato' => 'required|string|max:255',
        ]);

        $codigoCliente = $request->codigo_cliente;
        $numeroContrato = $request->numero_contrato;

        try {
            DB::beginTransaction();

            // Buscar el último seguimiento del expediente
            $ultimoSeguimiento = SeguimientoExpediente::where('id_expediente', $codigoCliente)
                ->orderBy('created_at', 'desc') // Asumiendo que created_at o id_seguimiento define el orden
                ->first();

            if (!$ultimoSeguimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró seguimiento para este expediente.'
                ], 404);
            }

            // Validar que esté en estado 3 (Aceptado por secretaría agencia)
            if ($ultimoSeguimiento->id_estado != 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'El expediente no se encuentra en el estado correcto (3) para adjuntar contrato.'
                ], 422);
            }

            // Actualizar el número de contrato
            $ultimoSeguimiento->numero_contrato = $numeroContrato;
            $ultimoSeguimiento->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Número de contrato adjuntado correctamente.',
                'data' => $ultimoSeguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al adjuntar contrato: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archivar Administrativamente (estado secundario).
     * Marca archivo_administrativo = 'Si'.
     */
    public function archivarAdministrativamente(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
        ]);

        $codigoCliente = $request->codigo_cliente;

        try {
            DB::beginTransaction();

            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $codigoCliente]);

            // Validar que esté aceptado (>= 3)
            if ($seguimiento->id_estado < 3) {
                 return response()->json([
                    'success' => false,
                    'message' => 'El expediente debe estar aceptado para archivar.'
                ], 422);
            }

            $seguimiento->archivo_administrativo = 'Si';
            $seguimiento->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente archivado administrativamente.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al archivar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buzón de Archivados Administrativamente.
     * Lista expedientes con archivo_administrativo = 'Si'.
     */
    public function buzonArchivados(Request $request)
    {
        $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->where('archivo_administrativo', 'Si');
        })
        ->with(['fechas', 'seguimientos.estado'])
        ->orderBy('fecha_inicio', 'desc')
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }
}
