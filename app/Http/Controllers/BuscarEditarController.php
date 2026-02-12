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

    // Buscamos el expediente con carga profunda de relaciones
    $expediente = NuevoExpediente::where('id', $search)
        ->orWhere('numero_documento', 'like', "%{$search}%")
        ->with([
            'garantias',
            'documentos.tipoDocumento',
            'documentos.registroPropiedad'
        ])
        ->first();

    if (!$expediente) {
        return response()->json(['success' => false], 404);
    }

    // Retornamos la estructura que tus componentes de Vue ya consumen
    return response()->json([
        'success' => true,
        'data' => [
            'expediente' => [
                'id' => $expediente->id,
                'codigo_cliente' => $expediente->codigo_cliente,
                'nombre_asociado' => $expediente->nombre_asociado,
                'numero_documento' => $expediente->numero_documento,
            ],
            'garantias' => $expediente->garantias,
            'documentos' => $expediente->documentos->map(function($doc) {
                $count = $doc->nuevosExpedientes()->count();

                // Si el conteo es mayor a 1, restamos el actual para mostrar "otros X"
                // Si es 1 o menos, el contador será 0 o 1 según prefieras
                $doc->expedientes_asociados_count = ($count > 1) ? $count - 1 : 0;

                return $doc;
            })
        ]
    ]);
    /**
     * Retorna la lista de expedientes asociados a un documento específico.
     * Carga diferida para no bloquear la búsqueda principal.
     */

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
