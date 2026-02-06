<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;

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
}
