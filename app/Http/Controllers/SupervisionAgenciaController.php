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

        // Parámetros de búsqueda y filtros
        $tab = $request->input('tab', 'nuevos'); // 'nuevos', 'seguimiento', 'finalizados'
        $asesorBusqueda = $request->input('asesor');
        $fechaInicioBusqueda = $request->input('fecha_inicio');

        // Buscamos expedientes de la agencia
        $query = NuevoExpediente::select([
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
            ])
            ->when(!$isSuperAdmin, function ($q) use ($idAgencia) {
                return $q->where('id_agencia', $idAgencia);
            })
            // Filtro por Fecha de Desembolso
            ->when($fechaInicioBusqueda, function ($q) use ($fechaInicioBusqueda) {
                return $q->whereDate('fecha_inicio', $fechaInicioBusqueda);
            })
            // Filtro por Asesor (búsqueda parcial ignorando mayúsculas/minúsculas)
            ->when($asesorBusqueda, function ($q) use ($asesorBusqueda) {
                return $q->where('usuario_asesor', 'like', '%' . $asesorBusqueda . '%');
            })
            // Lógica por Tabs
            ->when($tab === 'nuevos', function ($q) {
                // Cargados: No tienen registro en seguimiento_expedientes
                return $q->doesntHave('seguimientos');
            })
            ->when($tab === 'seguimiento', function ($q) {
                // En Seguimiento: Tienen seguimientos, pero el último NO es completado (11)
                return $q->whereHas('seguimientos', function ($q2) {
                    // Nos aseguramos que al menos exista uno
                })->whereDoesntHave('seguimientos', function ($q2) {
                    // Pero que el último no sea 11
                    $q2->whereRaw('id_seguimiento = (SELECT MAX(id_seguimiento) FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id)')
                       ->where(function ($q3) {
                           $q3->where('id_estado', 11)->orWhere('id_estado_secundario', 11);
                       });
                });
            })
            ->when($tab === 'finalizados', function ($q) {
                // Completados: El último seguimiento tiene estado 11 o secundario 11
                return $q->whereHas('seguimientos', function ($q2) {
                    $q2->whereRaw('id_seguimiento = (SELECT MAX(id_seguimiento) FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id)')
                       ->where(function ($q3) {
                           $q3->where('id_estado', 11)->orWhere('id_estado_secundario', 11);
                       });
                });
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
                     ->orderBy('id_seguimiento', 'desc')
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
