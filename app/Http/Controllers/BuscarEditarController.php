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
            ],
            'garantias' => $expediente->garantias,
            'documentos' => $expediente->documentos->map(function($doc) {
                // Calculamos el conteo para la validación de "Compartido"
                $doc->nuevos_expedientes_count = $doc->nuevosExpedientes()->count();
                return $doc;
            })
        ]
    ]);
}
}
