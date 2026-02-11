<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            'id' => 'required|exists:nuevos_expedientes,id',
        ]);

        try {
            DB::beginTransaction();

            $expedienteId = $request->id;

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
            'id' => 'required|exists:nuevos_expedientes,id',
            'bufete_id' => 'required|exists:bufetes,id',
        ]);

        try {
            DB::beginTransaction();

            $expedienteId = $request->id;

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
    /**
     * Obtener expedientes en manos de abogados (Estado 8, 9 y 10).
     * Incluye: Enviado a abogado (8), Recibido por abogado (9), Devuelto por abogado (10).
     */
    public function buzonAbogados(Request $request)
    {
        $expedientes = \App\Models\NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->whereIn('id_estado', [8, 9, 10])
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
     * Obtener expedientes devueltos por abogados (Estado 10) para escaneo.
     */
    public function escanearDocumentos(Request $request)
    {
        $query = NuevoExpediente::query();

        // Filtrar por el Último estado = 10 (Devuelto por Abogado) OR (Finalizado con es_un_pagare)
        $query->whereHas('seguimientos', function ($q) {
            $q->where(function ($sub) {
                $sub->where('id_estado', 10)
                    ->orWhere(function ($sub2) {
                        $sub2->whereIn('id_estado', [1, 4])
                             ->whereNotNull('es_un_pagare');
                    });
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
                $query->orderBy('created_at', 'desc')->with(['bufete.user', 'bufete.agencia']);
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
     * Guardar el archivo escaneado del expediente.
     */
    /**
     * Guardar el archivo escaneado del expediente (Contrato).
     */
    public function guardarEscaneado(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nuevos_expedientes,id',
            'file' => 'required|file|mimes:pdf|max:10240', // Max 10MB
        ]);

        try {
            DB::beginTransaction();

            $expedienteId = $request->id;
            $file = $request->file('file');

            // 1. Guardar el archivo físico
            // Sugerencia: Guardar en una carpeta específica, ej: 'expedientes/contratos_escaneados'
            $path = $file->store('expedientes/contratos_escaneados', 'public');

            // 2. Buscar el último seguimiento (Debería ser el estado 10)
            $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expedienteId)
                            ->orderBy('created_at', 'desc')
                            ->first();

            if (!$seguimiento) {
                // Should not happen if flow is correct
                throw new \Exception("No se encontró seguimiento para el expediente.");
            }

            // 3. Actualizar el campo path_contrato
            $seguimiento->path_contrato = $path;
            $seguimiento->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contrato escaneado guardado correctamente.',
                'data' => [
                    'path_contrato' => $path
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar contrato escaneado: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Ver/Descargar el contrato escaneado.
     */
    public function verContrato($id)
    {
        // 1. Buscar el último seguimiento con contrato
        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
                        ->whereNotNull('path_contrato')
                        ->orderBy('created_at', 'desc')
                        ->first();

        if (!$seguimiento || !Storage::disk('public')->exists($seguimiento->path_contrato)) {
            return response()->json(['message' => 'Contrato no encontrado.'], 404);
        }

        return Storage::disk('public')->download($seguimiento->path_contrato);
    }

    /**
     * Finalizar proceso de escaneo y determinar si es pagaré.
     */
    public function finalizarProceso(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nuevos_expedientes,id',
            'es_pagare' => 'required|in:si,no'
        ]);

        try {
            DB::beginTransaction();

            $id = $request->id;
            $esPagare = $request->es_pagare;

            // 1. Obtener el último seguimiento (Estado 10)
            $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
                            ->orderBy('created_at', 'desc')
                            ->first();

            if (!$seguimiento) {
                return response()->json(['success' => false, 'message' => 'Expediente no encontrado.'], 404);
            }

            // 2. Actualizar columna es_un_pagare y el estado
            $seguimiento->es_un_pagare = $esPagare;
            // Determine new state
            $nuevoEstado = ($esPagare === 'si') ? 1 : 4; // 5 = Protocolos, 4 = Archivo
            $seguimiento->id_estado = $nuevoEstado;
            $seguimiento->save();

            // 3. (Se eliminó la creación de un nuevo seguimiento, se trabaja sobre el actual)

            // 4. If "No" (State 4), update f_enviado_archivos in seguimiento_fechas
            if ($esPagare === 'no') {
                $fechas = \App\Models\SeguimientoFecha::firstOrCreate(
                    ['id_expediente' => $id]
                );
                $fechas->f_enviado_archivos = now();
                $fechas->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proceso finalizado correctamente.',
                'nuevo_estado' => $nuevoEstado
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar proceso: ' . $e->getMessage()
            ], 500);
        }
    }
}
