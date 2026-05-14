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
            'id' => 'required|exists:nuevos_expedientes,id',
            'numero_contrato' => 'required|string|max:255',
        ]);

        $id = $request->id;
        $numeroContrato = $request->numero_contrato;

        try {
            DB::beginTransaction();

            // Buscar el último seguimiento del expediente
            $ultimoSeguimiento = SeguimientoExpediente::where('id_expediente', $id)
                ->latest() // Atajo de Laravel para ->orderBy('created_at', 'desc')
                ->first();

            if (!$ultimoSeguimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró seguimiento para este expediente.'
                ], 404);
            }

            /**
             * CAMBIO CLAVE:
             * Ahora validamos que el estado sea 3 o superior (Aceptado, Protocolo, Archivo, etc.)
             */
            if ($ultimoSeguimiento->id_estado < 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'El expediente aún no ha sido aceptado (Estado ' . $ultimoSeguimiento->id_estado . '). Debe estar en estado 3 o superior.'
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
            'id' => 'required|exists:nuevos_expedientes,id',
        ]);

        $id = $request->id;

        try {
            DB::beginTransaction();

            $seguimiento = SeguimientoExpediente::firstOrNew(['id_expediente' => $id]);

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
        $query = NuevoExpediente::query();

        // 1. Filtrar por Agencia del Usuario (Excepto Super Admin)
        if (auth()->check()) {
            $user = auth()->user();
            $roles = $user->roles_list ?? [];
            $userAgenciaId = $user->id_agencia;

            if (!in_array('Super Admin', $roles) && $userAgenciaId) {
                $query->where('id_agencia', $userAgenciaId);
            }
        }

        // 2. Buscador
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo_cliente', 'LIKE', "%{$search}%")
                  ->orWhere('numero_documento', 'LIKE', "%{$search}%")
                  ->orWhere('nombre_asociado', 'LIKE', "%{$search}%");
            });
        }

        $expedientes = $query->whereHas('seguimientos', function ($q) {
            $q->where('archivo_administrativo', 'Si');
        })
        ->with(['fechas', 'seguimientos.estado', 'seguimientos.estadoSecundario'])
        ->orderBy('fecha_inicio', 'desc')
        ->paginate(15)
        ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }


}
