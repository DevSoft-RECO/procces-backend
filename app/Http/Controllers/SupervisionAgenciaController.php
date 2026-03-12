<?php

namespace App\Http\Controllers;

use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use Illuminate\Http\Request;

class SupervisionAgenciaController extends Controller
{
    /**
     * Listado histórico y general de expedientes para el Jefe de Agencia.
     * Muestra todos los expedientes de la agencia del usuario logueado.
     */
    public function index(Request $request)
    {
        $usuario = auth()->user();
        $idAgencia = $usuario->getAgenciaId();
        $isSuperAdmin = $usuario->hasRole('Super Admin');

        // Si el usuario no tiene agencia asignada y NO es Super Admin, retornamos vacío o error.
        // Aquí optamos por retornar una lista vacía para no romper el frontend.
        if (!$idAgencia && !$isSuperAdmin) {
             return response()->json([
                'success' => true,
                'data' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0
                ]
            ]);
        }

        // Buscamos expedientes de la agencia
        $query = NuevoExpediente::select([
                'id',
                'codigo_cliente',
                'id_agencia',
                'numero_documento',
                'usuario_asesor', // Campo nuevo solicitado
                'tasa_interes',
                'monto_documento',
                'tipo_garantia',
                'fecha_inicio',
                'cui',
                'nombre_asociado'
            ])
            ->when(!$isSuperAdmin, function ($q) use ($idAgencia) {
                return $q->where('id_agencia', $idAgencia);
            })
            // Agregamos el último seguimiento para conocer su estado actual
            ->with([
                 'seguimientos' => function ($query) {
                     $query->select([
                         'id_seguimiento',
                         'id_expediente',
                         'id_estado',
                         'id_estado_secundario',
                         'archivado_at',
                         'created_at'
                     ])
                     ->orderBy('created_at', 'desc')
                     ->limit(1);
                 }
            ])
            ->orderBy('id', 'desc');

        $expedientes = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }
}
