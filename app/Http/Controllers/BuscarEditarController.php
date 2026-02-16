<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;

class BuscarEditarController extends Controller
{
        /**
     * Busca un expediente por ID o número de documento y retorna
     * todos sus detalles para la vista de edición.
     */
    public function searchEdit(Request $request)
    {
        $search = $request->query('search');

        if (!$search) {
            return response()->json(['success' => false, 'message' => 'Criterio vacío'], 400);
        }

        // Estrategia: Buscar en SeguimientoExpediente para obtener el estado y el expediente asociado
        $query = \App\Models\SeguimientoExpediente::query()
            ->whereHas('nuevoExpediente', function($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('numero_documento', 'like', "%{$search}%");
            });

        // Filtro por Agencia (Permiso), EXCLUYENDO al Super Admin
        $user = auth()->user();
        if ($user && !$user->hasRole('Super Admin') && $user->hasPermissionTo('secretaria_agencia')) {
            $agenciaId = $user->getAgenciaId();
            if ($agenciaId) {
                // Filtramos sobre la relación nuevoExpediente
                $query->whereHas('nuevoExpediente', function($q) use ($agenciaId) {
                    $q->where('id_agencia', $agenciaId);
                });
            }
        }

        // Obtener el último seguimiento (el actual)
        $seguimiento = $query->with([
            'nuevoExpediente.garantias',
            'nuevoExpediente.documentos.tipoDocumento',
            'nuevoExpediente.documentos.registroPropiedad'
        ])
        ->orderBy('id_seguimiento', 'desc')
        ->first();

        // Validamos si se encontró seguimiento y su expediente
        if (!$seguimiento || !$seguimiento->nuevoExpediente) {
            return response()->json(['success' => false, 'message' => 'Expediente no encontrado'], 200);
        }

        $expediente = $seguimiento->nuevoExpediente;
        $estado = $seguimiento->id_estado;

        // Retornamos la estructura
        return response()->json([
            'success' => true,
            'data' => [
                'expediente' => [
                    'id' => $expediente->id,
                    'codigo_cliente' => $expediente->codigo_cliente,
                    'nombre_asociado' => $expediente->nombre_asociado,
                    'numero_documento' => $expediente->numero_documento,
                    'id_estado' => $estado, // Estado obtenido de la tabla seguimiento
                ],
                'garantias' => $expediente->garantias,
                'documentos' => $expediente->documentos->map(function($doc) {
                    $count = $doc->nuevosExpedientes()->count();
                    $doc->expedientes_asociados_count = ($count > 1) ? $count - 1 : 0;
                    return $doc;
                })
            ]
        ]);
    }

    public function getExpedientesAsociados($id)
    {
        $documento = \App\Models\Documento::with('nuevosExpedientes:id,numero_documento')->find($id);

        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $documento->nuevosExpedientes->pluck('numero_documento')
        ]);
    }
}
