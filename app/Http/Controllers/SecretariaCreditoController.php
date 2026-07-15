<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SecretariaCreditoController extends Controller
{
    protected $disk = 'gcs';

    /**
     * Listado de expedientes en estado 5 (Enviado a Protocolos).
     */
    public function index(Request $request)
    {
        // Query base
        $query = NuevoExpediente::query();

        // Determinamos qué estado filtrar. Por defecto 5, o 11 si se solicita.
        // Esto permite que la misma función maneje ambos casos.
        $estadoFiltrar = $request->get('estado', 5);

        // Filtrar por el Último estado (5 o 11)
        $query->whereHas('seguimientos', function ($q) use ($estadoFiltrar) {
            $q->where('id_estado', $estadoFiltrar)
            ->whereRaw('created_at = (
                SELECT MAX(s2.created_at)
                FROM seguimiento_expedientes as s2
                WHERE s2.id_expediente = seguimiento_expedientes.id_expediente
            )');
        });

        // Funcionalidad de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                ->orWhere('nombre_asociado', 'like', "%{$search}%")
                ->orWhere('codigo_cliente', 'like', "%{$search}%")
                ->orWhere('numero_documento', 'like', "%{$search}%")
                ->orWhere('cui', 'like', "%{$search}%");
            });
        }

        // Filtrar por Agencia
        if ($request->filled('id_agencia')) {
            $query->where('id_agencia', $request->id_agencia);
        }

        // Eager loading y paginación
        $expedientes = $query->with([
            'garantias',
            'documentos.tipoDocumento',
            'fechas',
            'seguimientos' => function($q) {
                $q->orderBy('id_seguimiento', 'desc');
            }
        ])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return response()->json([
            'success' => true,
            'estado_filtrado' => $estadoFiltrar,
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
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('numero_documento', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%");
            });
        }

        // Filtrar por Agencia
        if ($request->filled('id_agencia')) {
            $query->where('id_agencia', $request->id_agencia);
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
        $query = \App\Models\NuevoExpediente::select([
            'id',
            'codigo_cliente',
            'nombre_asociado',
            'id_agencia'
        ])
        ->whereHas('seguimientos', function ($query) {
            $query->whereIn('id_estado', [8, 9, 10])
                  ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.id)');
        })
        ->whereHas('fechas', function ($query) {
            $query->whereNull('f_enviado_secretaria_credito');
        });

        // Apply filters if any
        if ($request->filled('id_agencia')) {
            $query->where('id_agencia', $request->input('id_agencia'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('numero_documento', 'like', "%{$search}%");
            });
        }

        $expedientes = $query->with([
            'seguimientos' => function ($query) {
                $query->select(['id_expediente', 'bufete_id', 'id_estado', 'numero_contrato'])
                      ->orderBy('created_at', 'desc')
                      ->with([
                          'bufete:id,user_id,agencia_id',
                          'bufete.user:id,name',
                          'bufete.agencia:id,nombre',
                          'estado:id,nombre'
                      ]);
            },
            'fechas:id_expediente,f_aceptado_abogado,f_enviado_secretaria_credito,f_enviado_abogado',
            'agencia:id,nombre'
        ])
        ->latest('id')
        ->paginate(10);

        // Hide user appends
        $expedientes->getCollection()->transform(function ($expediente) {
            if ($expediente->seguimientos) {
                foreach ($expediente->seguimientos as $seg) {
                    if ($seg->bufete && $seg->bufete->user) {
                        $seg->bufete->user->makeHidden([
                            'roles', 'permissions', 'permisos', 
                            'roles_list', 'permissions_list', 'idagencia', 'id_agencia'
                        ]);
                    }
                }
            }
            return $expediente;
        });

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

        // Filtrar por el Último estado = 10 (Devuelto por Abogado)
        $query->whereHas('seguimientos', function ($q) {
            $q->where('id_estado', 10)
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
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('codigo_cliente', 'like', "%{$search}%")
                  ->orWhere('nombre_asociado', 'like', "%{$search}%")
                  ->orWhere('numero_documento', 'like', "%{$search}%")
                  ->orWhere('cui', 'like', "%{$search}%");
            });
        }

        // Filtrar por Agencia
        if ($request->filled('id_agencia')) {
            $query->where('id_agencia', $request->id_agencia);
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
            $expediente = NuevoExpediente::findOrFail($expedienteId);
            $file = $request->file('file');

            // 1. Buscar el último seguimiento (Debería ser el estado 10) para obtener el numero_contrato
            $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $expedienteId)
                            ->orderBy('created_at', 'desc')
                            ->first();

            if (!$seguimiento) {
                // Should not happen if flow is correct
                throw new \Exception("No se encontró seguimiento para el expediente.");
            }

            // Nomenclatura Estándar: CTO-numero_documento.ext
            $extension = $file->getClientOriginalExtension();
            $numDoc = $expediente->numero_documento ?? 'S-N';
            $filename = "CTO-{$numDoc}.{$extension}";

            // 2. Guardar el archivo en GCS bajo la carpeta principal sadec
            $folder = 'sadec/expedientes/contratos_escaneados';
            $path = Storage::disk($this->disk)->putFileAs($folder, $file, $filename);

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

        if (!$seguimiento || !Storage::disk($this->disk)->exists($seguimiento->path_contrato)) {
            return response()->json(['message' => 'Contrato no encontrado.'], 404);
        }

        // Generar URL firmada temporal por 15 segundos
        $url = Storage::disk($this->disk)->temporaryUrl(
            $seguimiento->path_contrato,
            now()->addSeconds(15)
        );

        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }

    /**
     * Finalizar proceso de escaneo y determinar el camino del expediente.
     */
public function finalizarProceso(Request $request)
{
    $request->validate([
        'id' => 'required|exists:nuevos_expedientes,id',
        'tipo_contrato' => 'required|in:Escritura Pública,Documento Privado,Pagaré'
    ]);

    try {
        DB::beginTransaction();

        $id = $request->id;
        $tipoContrato = $request->tipo_contrato;

        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
                        ->orderBy('created_at', 'desc')
                        ->first();

        if (!$seguimiento) {
            return response()->json(['success' => false, 'message' => 'Expediente no encontrado.'], 404);
        }

        // --- LÓGICA SIMPLIFICADA ---
        // 1. Guardar el tipo de contrato seleccionado
        $seguimiento->tipo_contrato = $tipoContrato;

        // 2. Establecer estados fijos solicitados
        $seguimiento->id_estado = 11; // Finalizado
        $seguimiento->id_estado_secundario = 4; // En Archivo (Buzón)

        // 3. Registrar fecha de envío a archivos
        $fechas = \App\Models\SeguimientoFecha::firstOrCreate(['id_expediente' => $id]);
        $fechas->f_enviado_archivos = now();
        $fechas->save();

        $seguimiento->save();
        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Proceso finalizado correctamente.',
            'tipo_contrato' => $tipoContrato
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
}
