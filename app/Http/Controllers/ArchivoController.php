<?php

namespace App\Http\Controllers;

use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchivoController extends Controller
{
    /**
     * Buzón de Recibidos en Archivo.
     * Lista expedientes donde el estado actual es 4 (Archivo)
     * O el estado secundario es 4 (Archivo Preliminar/Paralelo).
     */
 public function buzonRecibidos(Request $request)
{
    $expedientes = NuevoExpediente::select([
            'id',
            'codigo_cliente',
            'cui',
            'nombre_asociado',
            'tasa_interes',
            'monto_documento',
            'numero_documento',
            'fecha_inicio'
        ])
        ->whereHas('seguimientos', function ($query) {
            // 1. Condición de permanencia: Debe tener al menos uno en 4
            $query->where(function ($sub) {
                $sub->where('id_estado', 4)
                    ->orWhere('id_estado_secundario', 4);
            })
            // 2. CORRECCIÓN: Solo excluir si el SECUNDARIO ya llegó a 11
            // No importa si el principal ya es 11, mientras el secundario sea 4, debe seguir aquí.
            ->where('id_estado_secundario', '!=', 11)

            ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.id)');
        })
        ->with([
            'fechas:id_expediente,f_enviado_archivos',
            'seguimientos' => function ($query) {
                $query->select([
                    'id_seguimiento',
                    'id_expediente',
                    'observacion_envio',
                    'enviado_a_archivos',
                    'es_un_pagare',
                    'recibi_garantia_real',
                    'recibi_contrato',
                    'archivado_at',
                    'id_estado',
                    'id_estado_secundario',
                    'numero_contrato'
                ])
                ->orderBy('created_at', 'desc')
                ->limit(1);
            }
        ])
        ->orderBy('id', 'desc')
        ->paginate(15);

    return response()->json([
        'success' => true,
        'data' => $expedientes
    ]);
}


    /**
     * Marcar Garantía Real como recibida.
     * Solo si estado secundario es 4.
     */
    public function recibirGarantiaReal(Request $request, $id_expediente)
    {
        // Buscar el último seguimiento, o filtrar por el que tenga estado_secundario = 4?
        // Asumiremos que trabajamos sobre el último seguimiento activo del expediente.
        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id_expediente)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$seguimiento) {
            return response()->json(['success' => false, 'message' => 'No se encontró seguimiento para este expediente.'], 404);
        }

        // Validar lógica de negocio (opcional pero recomendada por seguridad)
        /*
        if ($seguimiento->id_estado_secundario != 4) {
             return response()->json(['success' => false, 'message' => 'El expediente no está en estado de recepción de garantía.'], 400);
        }
        */

        $seguimiento->update([
            'recibi_garantia_real' => 'Si - ' . now()->format('d/m/Y H:i')
        ]);

        return response()->json(['success' => true, 'message' => 'Garantía Real marcada como recibida.']);
    }

    /**
     * Marcar Contrato como recibido.
     * Solo si estado es 4.
     */
    public function recibirContrato(Request $request, $id_expediente)
    {
        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id_expediente)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$seguimiento) {
            return response()->json(['success' => false, 'message' => 'No se encontró seguimiento.'], 404);
        }

        // SEGURIDAD: Validar que realmente es un contrato antes de dejar marcar "recibido"
        if ($seguimiento->es_un_pagare !== 'no') {
            return response()->json(['success' => false, 'message' => 'Este expediente es un Pagaré, no requiere contrato.'], 400);
        }

        $seguimiento->update([
            'recibi_contrato' => 'Si - ' . now()->format('d/m/Y H:i')
        ]);

        return response()->json(['success' => true, 'message' => 'Contrato marcado como recibido.']);
    }

    /**
     * Archivar expediente.
     * Crea un registro en la tabla expedientes con la información del seguimiento
     * y finaliza el flujo secundario.
     */
    public function archivar(Request $request, $id_expediente)
    {
        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id_expediente)
            ->with(['nuevoExpediente.agencia'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$seguimiento) {
            return response()->json(['success' => false, 'message' => 'No se encontró seguimiento para este expediente.'], 404);
        }

        if ($seguimiento->archivado_at) {
             return response()->json(['success' => false, 'message' => 'El expediente ya fue archivado previamente.'], 400);
        }

        try {
            DB::beginTransaction(); // Añadimos transacción para asegurar consistencia

            $nuevoExpediente = $seguimiento->nuevoExpediente;
            if (!$nuevoExpediente) {
                 return response()->json(['success' => false, 'message' => 'No se encontró el expediente origen.'], 404);
            }

            $nombreAgencia = $nuevoExpediente->agencia ? $nuevoExpediente->agencia->nombre : 'N/A';

            // 1. Crear el registro histórico en la tabla de custodia final
            \App\Models\Expediente::create([
                'codigo_cliente'    => $nuevoExpediente->codigo_cliente,
                'agencia'           => $nombreAgencia,
                'fecha_inicio'      => $nuevoExpediente->fecha_inicio,
                'cta_bw'            => null,
                'numero_documento'  => $nuevoExpediente->numero_documento,
                'cif'               => null,
                'asociado'          => $nuevoExpediente->nombre_asociado,
                'monto'             => $nuevoExpediente->monto_documento,
                'tipo_garantia'     => $nuevoExpediente->tipo_garantia,
                'datos_garantia'    => null,
                'contrato'          => $seguimiento->numero_contrato,
                'inscripcion_otros_contratos' => null,
                'ingreso'           => now()->format('Y-m-d'),
                'estado'            => 'RECIBIDO',
            ]);

            // 2. ACTUALIZACIÓN CLAVE: Matar el proceso secundario
            // Al poner id_estado_secundario en 11, desaparece del buzón de Archivo.
            $seguimiento->update([
                'archivado_at'         => now(),
                'id_estado_secundario' => 11
                // 'archivo_administrativo' => 'Custodiado en Archivo'
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Expediente archivado y proceso finalizado en Archivo.']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Error archivando expediente $id_expediente: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al archivar el expediente: ' . $e->getMessage()], 500);
        }
    }



    /**
     * Listado general de expedientes finalizados (Sistema de Archivo).
     * Solo trae campos básicos para la tabla principal.
     */

    /**
     * Listado optimizado para el Sistema de Archivo (Expedientes en Estado 11).
     * Trae solo los campos necesarios de la tabla principal y el último seguimiento.
     */
/**
     * Listado ultra-optimizado para el Sistema de Archivo.
     * Solo devuelve los campos de la tabla principal.
     */
public function archivoSistema(Request $request)
{
    $expedientes = SeguimientoExpediente::select([
            'id_seguimiento',
            'id_expediente',
            'archivado_at'
        ])
        ->where('id_estado', 11)
        ->where('id_estado_secundario', 11)
        ->with([
            'nuevoExpediente' => function ($query) {
                $query->select([
                    'id',
                    'codigo_cliente',
                    'id_agencia',
                    'numero_documento',
                    'usuario_asesor',
                    'tasa_interes',
                    'monto_documento',
                    'tipo_garantia',
                    'fecha_inicio',
                    'cui',
                    'nombre_asociado'
                ]);
            }
        ])
        ->orderBy('id_seguimiento', 'desc')
        ->paginate(20);

    return response()->json([
        'success' => true,
        'data' => $expedientes
    ]);
}

    /**
     * Obtener únicamente la información técnica y legal que falta en el listado.
     * Optimizada para no repetir datos del expediente ya cargados en el buzon.
     */
public function show($id_seguimiento)
{
    $detalle = \App\Models\SeguimientoExpediente::select([
            'id_seguimiento',
            'id_expediente',
            'path_contrato',
            'bufete_id',
            'numero_contrato'
        ])
        ->with([
            'nuevoExpediente' => function ($query) {
                $query->select(['id', 'nombre_asociado']);
            },

            // CARGA ANIDADA: Detalle -> Garantía (Nombre)
            'nuevoExpediente.detalleGarantias' => function ($query) {
                $query->select([
                    'id', 'nuevo_expediente_id', 'garantia_id',
                    'codeudor1', 'codeudor2', 'codeudor3', 'codeudor4',
                    'observacion1', 'observacion2', 'observacion3', 'observacion4'
                ])->with(['garantia:id,nombre']); // <--- Agregamos esto
            },

            'nuevoExpediente.documentos' => function ($query) {
                $query->select([
                    'documentos.id', 'numero', 'fecha', 'propietario',
                    'autorizador', 'no_finca', 'folio', 'libro',
                    'no_dominio', 'referencia', 'monto_poliza', 'observacion'
                ]);
            },
            'bufete' => function ($query) {
                $query->select(['id', 'user_id']);
            },
            'bufete.user' => function ($query) {
                $query->select(['id', 'name']);
            }
        ])
        ->find($id_seguimiento);

    if (!$detalle) {
        return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
    }

    if ($detalle->nuevoExpediente && $detalle->nuevoExpediente->documentos) {
        $detalle->nuevoExpediente->documentos->makeHidden('pivot');
    }

    return response()->json(['success' => true, 'data' => $detalle]);
}

}
