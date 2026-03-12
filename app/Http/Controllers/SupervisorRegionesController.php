<?php

namespace App\Http\Controllers;

use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use Illuminate\Http\Request;

class SupervisorRegionesController extends Controller
{
    /**
     * Listado general de expedientes para el Supervisor de Regiones.
     * Permite ver todas las agencias y filtrar por ellas.
     */
    public function index(Request $request)
    {
        // Parámetros de búsqueda y filtros
        $tab = $request->input('tab', 'nuevos'); // 'nuevos', 'seguimiento', 'finalizados'
        $asesorBusqueda = $request->input('asesor');
        $fechaInicioBusqueda = $request->input('fecha_inicio');
        $agenciasIds = $request->input('agencias'); // Array de IDs de agencias

        // Buscamos expedientes
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
            // Filtro por Agencias (Multi-selección)
            ->when(!empty($agenciasIds), function ($q) use ($agenciasIds) {
                return $q->whereIn('id_agencia', (array)$agenciasIds);
            })
            // Filtro por Fecha de Desembolso
            ->when($fechaInicioBusqueda, function ($q) use ($fechaInicioBusqueda) {
                return $q->whereDate('fecha_inicio', $fechaInicioBusqueda);
            })
            // Filtro por Asesor
            ->when($asesorBusqueda, function ($q) use ($asesorBusqueda) {
                return $q->where('usuario_asesor', 'like', '%' . $asesorBusqueda . '%');
            })
            // Lógica por Tabs
            ->when($tab === 'nuevos', function ($q) {
                return $q->doesntHave('seguimientos');
            })
            ->when($tab === 'seguimiento', function ($q) {
                return $q->whereHas('seguimientos')->whereDoesntHave('seguimientos', function ($q2) {
                    $q2->whereRaw('id_seguimiento = (SELECT MAX(id_seguimiento) FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id)')
                       ->where(function ($q3) {
                           $q3->where('id_estado', 11)->orWhere('id_estado_secundario', 11);
                       });
                });
            })
            ->when($tab === 'finalizados', function ($q) {
                return $q->whereHas('seguimientos', function ($q2) {
                    $q2->whereRaw('id_seguimiento = (SELECT MAX(id_seguimiento) FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id)')
                       ->where(function ($q3) {
                           $q3->where('id_estado', 11)->orWhere('id_estado_secundario', 11);
                       });
                });
            })
            ->when($tab === 'rechazados', function ($q) {
                return $q->whereHas('seguimientos', function ($q2) {
                    $q2->whereRaw('id_seguimiento = (SELECT MAX(id_seguimiento) FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id)')
                       ->where('id_estado', 2);
                });
            })
            ->with([
                 'seguimientos' => function ($query) {
                     $query->select([
                         'id_seguimiento',
                         'id_expediente',
                         'id_estado',
                         'id_estado_secundario',
                         'observacion_rechazo',
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
