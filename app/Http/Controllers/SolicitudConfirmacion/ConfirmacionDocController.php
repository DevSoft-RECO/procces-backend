<?php

namespace App\Http\Controllers\SolicitudConfirmacion;

use App\Http\Controllers\Controller;
use App\Models\ConfirmacionDocumento;
use App\Models\Documento;
use App\Models\NuevoExpediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConfirmacionDocController extends Controller
{
    /**
     * Search for a document by number and date.
     */
    public function search(Request $request)
    {
        $numero = $request->input('numero');
        $fecha = $request->input('fecha');

        if (!$numero || !$fecha) {
            return response()->json(['message' => 'Número y fecha son requeridos.'], 400);
        }

        $documentos = Documento::with(['tipoDocumento', 'registroPropiedad', 'nuevosExpedientes'])
            ->where('numero', $numero)
            ->whereDate('fecha', $fecha)
            ->get();

        if ($documentos->count() > 0) {
            return response()->json([
                'found' => true,
                'multiple' => $documentos->count() > 1,
                'data' => $documentos->map(function ($documento) {
                    // Obtener el expediente activo más reciente vinculado al documento
                    $expediente = $documento->nuevosExpedientes
                        ->sortByDesc('id')
                        ->first();

                    return [
                        'id' => $documento->id,
                        'numero' => $documento->numero,
                        'fecha' => date('Y-m-d', strtotime($documento->fecha)), // Format date for input
                        'propietario' => $documento->propietario,
                        'autorizador' => $documento->autorizador,
                        'no_finca' => $documento->no_finca,
                        'folio' => $documento->folio,
                        'libro' => $documento->libro,
                        'no_dominio' => $documento->no_dominio,
                        'referencia' => $documento->referencia,
                        'monto_poliza' => $documento->monto_poliza,
                        'observacion' => $documento->observacion,
                        'tipo_documento' => $documento->tipoDocumento?->nombre,
                        'registro_propiedad' => $documento->registroPropiedad?->nombre,
                        // Datos del expediente vinculado
                        'codigo_cliente' => $expediente?->codigo_cliente,
                        'numero_producto' => $expediente?->numero_documento,
                        'id_expediente' => $expediente?->id,
                    ];
                })
            ]);
        }

        return response()->json(['found' => false, 'data' => []]);
    }

    /**
     * Store a confirmation request (User side).
     * Confirmacion and fecha_confirmacion are null initially.
     */
    public function store(Request $request)
    {
        $request->validate([
            'numero' => 'required',
            'fecha' => 'required|date',
            // No validation for confirmacion/fecha_confirmacion as they are admin fields
        ]);

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // Create with pending status (null fields) and assign to current user
            $data = $request->all();
            $data['user_id']           = $user->id;
            $data['nombre_solicitante'] = $user->name;  // Nombre del usuario que crea la solicitud
            $data['id_agencia']         = $request->input('id_agencia') ?? $user->id_agencia; // Agencia del usuario

            $confirmacion = ConfirmacionDocumento::create($data);

            DB::commit();

            return response()->json(['message' => 'Solicitud de confirmación enviada exitosamente.', 'data' => $confirmacion], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List pending confirmations (Admin side).
     */
    public function index(Request $request)
    {
        $query = ConfirmacionDocumento::with([
                'documento.tipoDocumento',
                'documento.registroPropiedad',
                'user',  // Nombre y agencia del solicitante
            ])
            ->whereNull('confirmacion');

        $search = $request->input('search');
        if ($search) {
            $query->where('id', $search);
        }

        $pendientes = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($pendientes);
    }

    /**
     * Update confirmation (Admin side - Validation).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'confirmacion' => 'required|in:SI,NO',
            'observacion_confirmacion' => 'nullable|string',
        ]);

        try {
            $confirmacion = ConfirmacionDocumento::findOrFail($id);

            $confirmacion->update([
                'confirmacion' => $request->confirmacion,
                'observacion_confirmacion' => $request->observacion_confirmacion,
                'fecha_confirmacion' => now(),
                'archivado' => true // Auto-archive on validation
            ]);

            return response()->json(['message' => 'Documento validado correctamente.', 'data' => $confirmacion]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al validar documento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List confirmed results (Requester View).
     */
    public function indexResults(Request $request)
    {
        $query = ConfirmacionDocumento::with([
            'documento.tipoDocumento',
            'documento.registroPropiedad',
            'documento.nuevosExpedientes' => function ($query) {
                $query->select('nuevos_expedientes.id', 'nuevos_expedientes.numero_documento', 'nuevos_expedientes.nombre_asociado', 'nuevos_expedientes.codigo_cliente');
            },
            'user',  // Nombre y agencia del solicitante
        ])
            // Include pending requests too, ordered by date
            ->orderBy('created_at', 'desc');

        // Filter by user unless Super Admin
        $user = $request->user();
        if (!$user || !in_array('Super Admin', $user->roles_list ?? [])) {
            $query->where('user_id', $user->id);
        }

        $search = $request->input('search');
        if ($search) {
            $query->where('id', $search);
        }

        $resultados = $query->paginate(10);

        return response()->json($resultados);
    }

    /**
     * List validation history (Validator View).
     */
    public function indexHistory(Request $request)
    {
        // For now, same data. In future, could filter byValidatorUser
        $historico = ConfirmacionDocumento::with([
            'documento.tipoDocumento',
            'documento.registroPropiedad',
            'documento.nuevosExpedientes' => function ($query) {
                $query->select('nuevos_expedientes.id', 'nuevos_expedientes.numero_documento', 'nuevos_expedientes.nombre_asociado', 'nuevos_expedientes.codigo_cliente');
            },
            'user',  // Nombre y agencia del solicitante
        ])
            ->whereNotNull('confirmacion')
            ->orderBy('fecha_confirmacion', 'desc')
            ->paginate(10);

        return response()->json($historico);
    }

    /**
     * Archive a confirmation result.
     */
    public function archive(Request $request, $id)
    {
        try {
            $confirmacion = ConfirmacionDocumento::findOrFail($id);
            $confirmacion->update(['archivado' => true]);

            return response()->json(['message' => 'Documento archivado correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al archivar documento: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Delete a confirmation request (Super Admin only).
     */
    public function destroy($id)
    {
        try {
            $confirmacion = ConfirmacionDocumento::findOrFail($id);
            $confirmacion->delete();
            return response()->json(['message' => 'Solicitud eliminada correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la solicitud: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Register a new document from a manual confirmation request.
     */
    public function registerDocument(Request $request, $id)
    {
        $confirmacion = ConfirmacionDocumento::findOrFail($id);

        if ($confirmacion->documento_id) {
            return response()->json(['message' => 'Esta solicitud ya tiene un documento asociado.'], 400);
        }

        $request->validate([
            'numero' => 'required',
            'fecha' => 'required|date',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'registro_propiedad_id' => 'nullable|exists:registro_propiedads,id',
        ]);

        try {
            DB::beginTransaction();

            $documento = Documento::create([
                'numero' => $request->numero,
                'fecha' => $request->fecha,
                'propietario' => $request->propietario,
                'autorizador' => $request->autorizador,
                'no_finca' => $request->no_finca,
                'folio' => $request->folio,
                'libro' => $request->libro,
                'no_dominio' => $request->no_dominio,
                'referencia' => $request->referencia,
                'monto_poliza' => $request->monto_poliza,
                'observacion' => $request->observacion,
                'tipo_documento_id' => $request->tipo_documento_id,
                'registro_propiedad_id' => $request->registro_propiedad_id,
            ]);

            // Associate document with confirmation
            $confirmacion->documento_id = $documento->id;

            // Update cached fields in confirmation to equal the definitive document fields
            $confirmacion->tipo_documento = $documento->tipoDocumento->nombre;
            $confirmacion->registro_propiedad = $documento->registroPropiedad ? $documento->registroPropiedad->nombre : null;

            $confirmacion->save();

            DB::commit();

            return response()->json(['message' => 'Documento registrado y vinculado exitosamente.', 'data' => $documento]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar documento: ' . $e->getMessage()], 500);
        }
    }
}
