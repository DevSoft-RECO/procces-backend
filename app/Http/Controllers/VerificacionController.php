<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Documento;
use Illuminate\Support\Facades\Auth;

class VerificacionController extends Controller
{
    /**
     * Verificar garantías y sus expedientes asociados.
     */
    public function verify(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Este módulo es exclusivo para Super Admin.'
            ], 403);
        }

        $documentoId = $request->input('documento_id');
        $numero = trim($request->input('numero'));
        $fecha = trim($request->input('fecha'));

        if ($documentoId) {
            $documento = Documento::find($documentoId);
            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía no encontrada.'
                ], 404);
            }
            $documentos = collect([$documento]);
        } else {
            if (!$numero || !$fecha) {
                return response()->json([
                    'success' => false,
                    'message' => 'El número y la fecha son obligatorios.'
                ], 400);
            }

            // Consulta eficiente filtrando por número y fecha
            $documentos = Documento::select('id', 'numero', 'fecha', 'propietario')
                ->where('numero', $numero)
                ->where('fecha', $fecha)
                ->get();

            if ($documentos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ninguna garantía con el número y fecha proporcionados.'
                ]);
            }
        }

        // Si hay múltiples garantías con el mismo número y fecha, pero diferente propietario,
        // y el usuario aún no ha seleccionado una garantía específica.
        if ($documentos->count() > 1 && !$documentoId) {
            return response()->json([
                'success' => true,
                'multiple' => true,
                'data' => $documentos
            ]);
        }

        // Procesar la garantía seleccionada o la única encontrada
        $selectedDoc = $documentos->first();

        // Carga eficiente de la relación con columnas específicas
        $selectedDoc->load(['nuevosExpedientes' => function ($query) {
            $query->select('nuevos_expedientes.id', 'nuevos_expedientes.codigo_cliente', 'nuevos_expedientes.numero_documento');
        }]);

        $productos = $selectedDoc->nuevosExpedientes;

        if ($productos->isEmpty()) {
            return response()->json([
                'success' => true,
                'multiple' => false,
                'garantia' => [
                    'id' => $selectedDoc->id,
                    'numero' => $selectedDoc->numero,
                    'fecha' => $selectedDoc->fecha,
                    'propietario' => $selectedDoc->propietario,
                ],
                'productos' => [],
                'message' => 'Ningun producto Asociado a la garantia'
            ]);
        }

        // Mapear los productos
        $productosMapeados = $productos->map(function ($prod) {
            return [
                'id' => $prod->id,
                'codigo_cliente' => $prod->codigo_cliente,
                'numero_documento' => $prod->numero_documento, // El número de producto/crédito
                'estado_pivote' => $prod->pivot->estado ?? 'activo',
            ];
        });

        return response()->json([
            'success' => true,
            'multiple' => false,
            'garantia' => [
                'id' => $selectedDoc->id,
                'numero' => $selectedDoc->numero,
                'fecha' => $selectedDoc->fecha,
                'propietario' => $selectedDoc->propietario,
            ],
            'productos' => $productosMapeados
        ]);
    }
}
