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
        $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->where(function ($sub) {
                $sub->where('archivo_administrativo', 'Si');
            });
        })
        ->with(['fechas', 'seguimientos.estado', 'seguimientos.estadoSecundario'])
        ->orderBy('fecha_inicio', 'desc')
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }
    /**
     * Buzón de Pagarés (Estado 1 y es_un_pagare = 'si').
     */
    // public function buzonPagares(Request $request)
    // {
    //     $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) {
    //         $query->where('id_estado', 1)
    //               ->where('es_un_pagare', 'si')
    //               ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.codigo_cliente)');
    //     })
    //     ->with(['fechas', 'seguimientos' => function ($query) {
    //         $query->orderBy('created_at', 'desc')->with('estado');
    //     }])
    //     ->orderBy('fecha_inicio', 'desc')
    //     ->paginate(15);

    //     return response()->json([
    //         'success' => true,
    //         'data' => $expedientes
    //     ]);
    // }
    /**
     * Marcar Pagaré como Recibido.
     */
    public function recibirPagare(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nuevos_expedientes,id'
        ]);

        $id = $request->id;

        try {
            DB::beginTransaction();

            // Buscar el último seguimiento del expediente
            $seguimiento = SeguimientoExpediente::where('id_expediente', $id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$seguimiento) {
                return response()->json(['success' => false, 'message' => 'Seguimiento no encontrado.'], 404);
            }

            // Actualizar campo
            $seguimiento->recibi_pagare = 'si';
            $seguimiento->id_estado = 11; // Cambiar a estado 6 (Archivado)
            $seguimiento->save();

            // Registrar fecha de almacenado administrativo
            \App\Models\SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $id],
                ['f_almacenado_admin' => \Carbon\Carbon::now()]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pagaré marcado como recibido.',
                'data' => $seguimiento
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al recibir pagaré: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Archivar Pagaré (Estado 6).
     */
    // public function archivarPagare(Request $request)
    // {
    //     $request->validate([
    //         'codigo_cliente' => 'required|exists:nuevos_expedientes,codigo_cliente'
    //     ]);

    //     $codigo = $request->codigo_cliente;

    //     try {
    //         DB::beginTransaction();

    //         // Buscar el último seguimiento del expediente
    //         $seguimiento = SeguimientoExpediente::where('id_expediente', $codigo)
    //             ->orderBy('created_at', 'desc')
    //             ->first();

    //         if (!$seguimiento) {
    //             return response()->json(['success' => false, 'message' => 'Seguimiento no encontrado.'], 404);
    //         }

    //         // Cambiar estado a 6 (Archivado) y marcar como administrativo
    //         $seguimiento->id_estado = 6;
    //         $seguimiento->archivo_administrativo = 'Si';
    //         $seguimiento->save();

    //         // Registrar fecha de almacenado administrativo
    //         \App\Models\SeguimientoFecha::updateOrCreate(
    //             ['id_expediente' => $codigo],
    //             ['f_almacenado_admin' => \Carbon\Carbon::now()]
    //         );

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Expediente archivado correctamente.',
    //             'data' => $seguimiento
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al archivar pagaré: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
}
