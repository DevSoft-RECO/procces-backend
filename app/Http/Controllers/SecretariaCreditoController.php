<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use Illuminate\Support\Facades\DB;

class SecretariaCreditoController extends Controller
{
    /**
     * Listado de expedientes en estado 5 (Enviado a Protocolos).
     */
    public function index(Request $request)
    {
        // Query base
        $query = NuevoExpediente::query();

        // Filtrar por el Último estado = 5
        // Usamos whereHas con una subquery para asegurar que sea el ULTIMO seguimiento
        $query->whereHas('seguimientos', function ($q) {
            $q->where('id_estado', 5)
              ->whereRaw('created_at = (
                  SELECT MAX(s2.created_at)
                  FROM seguimiento_expedientes as s2
                  WHERE s2.id_expediente = seguimiento_expedientes.id_expediente
              )');
        });

        // Search functionality (optional but good to have)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%");
            });
        }

        // Eager loading similar to NuevoExpedienteController
        $expedientes = $query->with([
            'garantias',
            'documentos.tipoDocumento',
            'fechas', // Eager load 'fechas' relationship
            'seguimientos' => function($query) {
                $query->orderBy('id_seguimiento', 'desc');
            }
        ])
        ->orderBy('created_at', 'desc') // Or order by modification date
        ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }
    /**
     * Aceptar expediente (Pasar a estado 7).
     * Modifica el registro existente, cambiando id_estado a 7.
     */
    public function aceptar(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
        ]);

        try {
            DB::beginTransaction();

            $expedienteId = $request->codigo_cliente;

            // 1. Buscar el último seguimiento existente
            $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expedienteId)
                            ->orderBy('created_at', 'desc') // Asumimos que queremos modificar el actual/último
                            ->first();

            if (!$seguimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró seguimiento para este expediente.'
                ], 404);
            }

            // 2. Solo cambiar el estado a 7
            $seguimiento->id_estado = 7;
            $seguimiento->save();

            // 3. Actualizar fechas
            \App\Models\SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $expedienteId],
                ['f_aceptado_secretaria_credito' => now()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente aceptado correctamente (Estado y fecha actualizados).'
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
     * Listado de expedientes en estado 7 (Aceptados).
     */
    public function buzonAceptados(Request $request)
    {
        $query = NuevoExpediente::query();

        // Filtrar por el Último estado = 7
        $query->whereHas('seguimientos', function ($q) {
            $q->where('id_estado', 7)
              ->where(function ($sub) {
                  $sub->where('id_estado_secundario', '!=', 6)
                      ->orWhereNull('id_estado_secundario');
              })
              ->whereRaw('created_at = (
                  SELECT MAX(s2.created_at)
                  FROM seguimiento_expedientes as s2
                  WHERE s2.id_expediente = seguimiento_expedientes.id_expediente
              )');
        });

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%");
            });
        }

        $expedientes = $query->with([
            'garantias',
            'documentos.tipoDocumento',
            'fechas',
            'seguimientos' => function($query) {
                $query->orderBy('id_seguimiento', 'desc');
            }
        ])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }
    /**
     * Enviar expediente a Abogado (Estado 8).
     */
    public function enviarAbogado(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente',
            'bufete_id' => 'required|exists:bufetes,id',
        ]);

        try {
            DB::beginTransaction();

            $expedienteId = $request->codigo_cliente;

            // 1. Buscar el último seguimiento existente
            $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expedienteId)
                            ->orderBy('created_at', 'desc')
                            ->first();

            if (!$seguimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró seguimiento para este expediente.'
                ], 404);
            }

            // 2. Cambiar id_estado a 8 y guardar bufete
            $seguimiento->id_estado = 8; // 8: En manos de abogado / Jurídico
            $seguimiento->bufete_id = $request->bufete_id;
            $seguimiento->save();

            // 3. Actualizar fecha f_enviado_abogado
            \App\Models\SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $expedienteId],
                ['f_enviado_abogado' => now()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expediente enviado a abogado correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar a abogado: ' . $e->getMessage()
            ], 500);
        }
    }
}
