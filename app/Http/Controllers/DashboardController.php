<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\SeguimientoFecha;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Helper to get the authorized agency ID.
     */
    private function getAuthorizedAgencyId(Request $request)
    {
         $user = Auth::user();
         if ($user->hasRole('Super Admin') || $user->hasPermissionTo('dashboard_general')) {
             $agencyIds = $request->input('agency_id');
             if ($agencyIds) {
                 return is_array($agencyIds) ? $agencyIds : [$agencyIds];
             }
             return null; // Can be null (all agencies)
         }
         return [$user->id_agencia]; // Force user's agency using array wrapper
    }

    /**
     * Get High-Level KPIs
     */
    public function kpi(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();
        $agencyIds = $this->getAuthorizedAgencyId($request);

        // 1. Consolidated query for most KPIs
        $query = DB::table('nuevos_expedientes')
            ->whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth]);

        if ($agencyIds) {
            $query->whereIn('id_agencia', $agencyIds);
        }

        $metrics = $query->select(
            DB::raw('COUNT(*) as total_mes'),
            DB::raw('SUM(CASE WHEN EXISTS (SELECT 1 FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id) THEN 1 ELSE 0 END) as con_seguimiento'),
            DB::raw('SUM(CASE WHEN EXISTS (SELECT 1 FROM seguimiento_expedientes WHERE id_expediente = nuevos_expedientes.id AND id_estado = 11) THEN 1 ELSE 0 END) as total_finalized'),
            DB::raw('SUM(monto_documento) as total_amount')
        )->first();

        // 2. Average Days (Still requires joins, but one query)
        $avgDaysQuery = DB::table('nuevos_expedientes')
            ->join('seguimiento_fechas', 'nuevos_expedientes.id', '=', 'seguimiento_fechas.id_expediente')
            ->whereBetween('nuevos_expedientes.fecha_inicio', [$startOfMonth, $endOfMonth])
            ->whereExists(function($q) {
                $q->select(DB::raw(1))
                  ->from('seguimiento_expedientes')
                  ->whereColumn('seguimiento_expedientes.id_expediente', 'nuevos_expedientes.id')
                  ->where('seguimiento_expedientes.id_estado', 11);
            })
            ->whereNotNull('seguimiento_fechas.f_almacenado_admin');

        if ($agencyIds) {
            $avgDaysQuery->whereIn('nuevos_expedientes.id_agencia', $agencyIds);
        }

        $avgDaysOpen = $avgDaysQuery->avg(DB::raw('DATEDIFF(seguimiento_fechas.f_almacenado_admin, nuevos_expedientes.fecha_inicio)'));

        return response()->json([
            'total_mes'        => (int)$metrics->total_mes,
            'con_seguimiento'  => (int)$metrics->con_seguimiento,
            'total_finalized'  => (int)$metrics->total_finalized,
            'total_amount'     => (float)$metrics->total_amount,
            'avg_days_open'    => round($avgDaysOpen, 1),
            'total_active'     => (int)$metrics->con_seguimiento, // Legacy compat
        ]);
    }

    /**
     * Get Pipeline Distribution (Bottlenecks)
     */
    public function pipeline(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();
        $agencyIds = $this->getAuthorizedAgencyId($request);

        // Group by Current State
        $query = NuevoExpediente::join('seguimiento_expedientes', 'nuevos_expedientes.id', '=', 'seguimiento_expedientes.id_expediente')
            ->join('tipo_estados', 'seguimiento_expedientes.id_estado', '=', 'tipo_estados.id')
            ->whereBetween('nuevos_expedientes.fecha_inicio', [$startOfMonth, $endOfMonth])
            ->select('tipo_estados.nombre as state_name', DB::raw('count(*) as count'))
            ->where('seguimiento_expedientes.id_estado', '!=', 11); // Exclude Finalized for pipeline view

        if ($agencyIds) {
            $query->whereIn('nuevos_expedientes.id_agencia', $agencyIds);
        }

        $distribution = $query->groupBy('tipo_estados.nombre')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json($distribution);
    }

    /**
     * Get Advisor Performance (Paginated)
     */
    public function advisors(Request $request)
    {
        $agencyIds = $this->getAuthorizedAgencyId($request);
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

        // 1. aggregated query to get all metrics per advisor in ONE query
        $query = DB::table('nuevos_expedientes')
            ->select(
                DB::raw('LOWER(nuevos_expedientes.usuario_asesor) as advisor_id'),
                DB::raw('users.name as advisor_name'),
                DB::raw('COUNT(*) as total_cases'),
                DB::raw('SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM seguimiento_expedientes 
                    WHERE seguimiento_expedientes.id_expediente = nuevos_expedientes.id 
                    AND seguimiento_expedientes.id_estado != 11
                ) THEN 1 ELSE 0 END) as active_cases'),
                DB::raw('SUM(
                    (CASE WHEN EXISTS (SELECT 1 FROM seguimiento_fechas sf WHERE sf.id_expediente = nuevos_expedientes.id AND sf.f_retorno_asesores IS NOT NULL) THEN 1 ELSE 0 END) +
                    (CASE WHEN EXISTS (SELECT 1 FROM seguimiento_expedientes se WHERE se.id_expediente = nuevos_expedientes.id AND se.modificacion = 1) THEN 1 ELSE 0 END)
                ) as rejected_cases'),
                DB::raw('SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM seguimiento_expedientes 
                    WHERE seguimiento_expedientes.id_expediente = nuevos_expedientes.id
                ) THEN 1 ELSE 0 END) as pending_cases')
            )
            ->leftJoin('users', DB::raw('LOWER(users.username)'), '=', DB::raw('LOWER(nuevos_expedientes.usuario_asesor)'))
            ->whereBetween('nuevos_expedientes.fecha_inicio', [$startOfMonth, $endOfMonth])
            ->whereNotNull('nuevos_expedientes.usuario_asesor');

        if ($agencyIds) {
            $query->whereIn('nuevos_expedientes.id_agencia', $agencyIds);
        }

        $results = $query->groupBy(DB::raw('LOWER(nuevos_expedientes.usuario_asesor)'), 'users.name')
            ->orderBy('active_cases', 'desc')
            ->get();

        $metrics = $results->map(function($row) {
            $total = (int)$row->total_cases;
            $rejected = (int)$row->rejected_cases;
            $active = (int)$row->active_cases;
            $pending = (int)$row->pending_cases;

            return [
                'asesor' => $row->advisor_name ?? $row->advisor_id,
                'advisor_id' => $row->advisor_id,
                'active_cases' => $active,
                'rejected_cases' => $rejected,
                'total_cases' => $total,
                'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 1) : 0,
                'success_rate' => $total > 0 ? round((($total - $rejected) / $total) * 100, 1) : 0,
                'clean_cases' => $total - $rejected,
                'pending_cases' => $pending
            ];
        });

        // Pagination
        $page = $request->input('page', 1);
        $perPage = 10;
        $totalItems = $metrics->count();
        $items = $metrics->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => $perPage,
            'total' => $totalItems,
            'last_page' => (int)ceil($totalItems / $perPage)
        ]);
    }

    public function agenciesList() {
        return response()->json(\App\Models\Agencia::select('id', 'nombre')->orderBy('nombre')->get());
    }

    /**
     * Deep Dive into Rejections (Breakdown by Agency/Advisor)
     */
    public function rejections()
    {
        // We want to see WHICH agencies/advisors have the most defects.
        // We query expedientes that have EVER been returned.

        $rejections = NuevoExpediente::with(['agencia'])
            ->select(
                'usuario_asesor',
                'id_agencia',
                DB::raw('SUM(
                    (CASE WHEN EXISTS (SELECT 1 FROM seguimiento_fechas sf WHERE sf.id_expediente = nuevos_expedientes.id AND sf.f_retorno_asesores IS NOT NULL) THEN 1 ELSE 0 END) +
                    (CASE WHEN EXISTS (SELECT 1 FROM seguimiento_expedientes se WHERE se.id_expediente = nuevos_expedientes.id AND se.modificacion = 1) THEN 1 ELSE 0 END)
                ) as count')
            )
            ->groupBy('usuario_asesor', 'id_agencia')
            ->having('count', '>', 0)
            ->get()
            ->map(function($item) {
                return [
                    'asesor' => $item->usuario_asesor,
                    'agencia' => $item->agencia ? $item->agencia->nombre : 'Sin Agencia',
                    'rejections' => $item->count
                ];
            })
            ->sortByDesc('rejections')
            ->values();

        return response()->json($rejections);
    }

    /**
     * Agency Performance
     */
    public function agencies(Request $request)
    {
        $agencyIds = $this->getAuthorizedAgencyId($request);
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

        // Single aggregated query for ALL agencies
        $query = DB::table('agencias')
            ->leftJoin('nuevos_expedientes', function($join) use ($startOfMonth, $endOfMonth) {
                $join->on('agencias.id', '=', 'nuevos_expedientes.id_agencia')
                     ->whereBetween('nuevos_expedientes.fecha_inicio', [$startOfMonth, $endOfMonth]);
            })
            ->select(
                'agencias.nombre as agency_name',
                DB::raw('COUNT(nuevos_expedientes.id) as total_cases'),
                DB::raw('SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM seguimiento_expedientes 
                    WHERE seguimiento_expedientes.id_expediente = nuevos_expedientes.id 
                    AND seguimiento_expedientes.id_estado != 11
                ) THEN 1 ELSE 0 END) as active_cases'),
                DB::raw('SUM(
                    (CASE WHEN EXISTS (SELECT 1 FROM seguimiento_fechas sf WHERE sf.id_expediente = nuevos_expedientes.id AND sf.f_retorno_asesores IS NOT NULL) THEN 1 ELSE 0 END) +
                    (CASE WHEN EXISTS (SELECT 1 FROM seguimiento_expedientes se WHERE se.id_expediente = nuevos_expedientes.id AND se.modificacion = 1) THEN 1 ELSE 0 END)
                ) as rejected_cases'),
                DB::raw('SUM(CASE WHEN nuevos_expedientes.id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM seguimiento_expedientes 
                    WHERE seguimiento_expedientes.id_expediente = nuevos_expedientes.id
                ) THEN 1 ELSE 0 END) as pending_cases')
            );

        if ($agencyIds) {
            $query->whereIn('agencias.id', $agencyIds);
        }

        $results = $query->groupBy('agencias.nombre')
            ->orderBy('active_cases', 'desc')
            ->get();

        $data = $results->filter(function($row) {
            return (int)$row->total_cases > 0;
        })->map(function($row) {
            $total = (int)$row->total_cases;
            $rejected = (int)$row->rejected_cases;
            $active = (int)$row->active_cases;
            $pending = (int)$row->pending_cases;

            return [
                'agency' => $row->agency_name,
                'active' => $active,
                'rejected_cases' => $rejected,
                'total' => $total,
                'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 1) : 0,
                'success_rate' => $total > 0 ? round((($total - $rejected) / $total) * 100, 1) : 0,
                'pending_cases' => $pending
            ];
        });

        // Pagination
        $page = $request->input('page', 1);
        $perPage = 10;
        $totalCount = $data->count();
        $items = $data->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'last_page' => (int)ceil($totalCount / $perPage)
        ]);
    }

    /**
     * Trends (Last 6 Months)
     */
    public function trends(Request $request)
    {
        $agencyIds = $this->getAuthorizedAgencyId($request);
        $startDate = Carbon::now()->subMonths(5)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // 1. Get Created counts grouped by Month
        $createdQuery = DB::table('nuevos_expedientes')
            ->select(
                DB::raw("DATE_FORMAT(fecha_inicio, '%Y-%m') as month_label"),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('fecha_inicio', [$startDate, $endDate]);

        if ($agencyIds) {
            $createdQuery->whereIn('id_agencia', $agencyIds);
        }

        $createdData = $createdQuery->groupBy('month_label')->pluck('count', 'month_label');

        // 2. Get Finalized counts grouped by Month
        $finalizedQuery = DB::table('seguimiento_fechas')
            ->join('nuevos_expedientes', 'seguimiento_fechas.id_expediente', '=', 'nuevos_expedientes.id')
            ->select(
                DB::raw("DATE_FORMAT(seguimiento_fechas.f_almacenado_admin, '%Y-%m') as month_label"),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('seguimiento_fechas.f_almacenado_admin', [$startDate, $endDate]);

        if ($agencyIds) {
            $finalizedQuery->whereIn('nuevos_expedientes.id_agencia', $agencyIds);
        }

        $finalizedData = $finalizedQuery->groupBy('month_label')->pluck('count', 'month_label');

        // 3. Assemble the last 6 months list
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $label = $date->format('Y-m');
            
            $months[] = [
                'month' => $label,
                'created' => $createdData->get($label, 0),
                'finalized' => $finalizedData->get($label, 0)
            ];
        }

        return response()->json($months);
    }
    public function processingTimes(Request $request)
    {
        $agencyIds = $this->getAuthorizedAgencyId($request);
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($month)->startOfMonth();
        $endOfMonth = Carbon::parse($month)->endOfMonth();

        // Calculamos el promedio excluyendo operaciones que resulten negativas (indicativo de que
        // un documento re-entró a etapas previas (rebotes/devoluciones) regrabando la fecha posterior).
        // Usamos TIMESTAMPDIFF(HOUR) / 24.0 para obtener promedios decimales correctos incluso en el mismo día.

        $query = DB::table('nuevos_expedientes')
            ->join('seguimiento_fechas', 'nuevos_expedientes.id', '=', 'seguimiento_fechas.id_expediente')
            ->whereBetween('nuevos_expedientes.fecha_inicio', [$startOfMonth, $endOfMonth]);

        if ($agencyIds) {
            $query->whereIn('nuevos_expedientes.id_agencia', $agencyIds);
        }

        $avgs = $query->select(
                DB::raw('AVG(CASE WHEN seguimiento_fechas.f_enviado_secretaria >= nuevos_expedientes.created_at THEN TIMESTAMPDIFF(HOUR, nuevos_expedientes.created_at, seguimiento_fechas.f_enviado_secretaria) / 24.0 ELSE NULL END) as avg_creation_to_secretary'),
                DB::raw('AVG(CASE WHEN seguimiento_fechas.f_enviado_protocolos >= seguimiento_fechas.f_aceptado_secretaria THEN TIMESTAMPDIFF(HOUR, seguimiento_fechas.f_aceptado_secretaria, seguimiento_fechas.f_enviado_protocolos) / 24.0 ELSE NULL END) as avg_secretary_internal'),
                DB::raw('AVG(CASE WHEN seguimiento_fechas.f_enviado_abogado >= seguimiento_fechas.f_aceptado_secretaria_credito THEN TIMESTAMPDIFF(HOUR, seguimiento_fechas.f_aceptado_secretaria_credito, seguimiento_fechas.f_enviado_abogado) / 24.0 ELSE NULL END) as avg_secretary_to_lawyer'),
                DB::raw('AVG(CASE WHEN seguimiento_fechas.f_enviado_secretaria_credito >= seguimiento_fechas.f_aceptado_abogado THEN TIMESTAMPDIFF(HOUR, seguimiento_fechas.f_aceptado_abogado, seguimiento_fechas.f_enviado_secretaria_credito) / 24.0 ELSE NULL END) as avg_lawyer_return')
            )
            ->first();

        return response()->json([
            'creation_to_secretary' => round($avgs->avg_creation_to_secretary ?? 0, 1),
            'secretary_internal' => round($avgs->avg_secretary_internal ?? 0, 1),
            'secretary_to_lawyer' => round($avgs->avg_secretary_to_lawyer ?? 0, 1),
            'lawyer_return' => round($avgs->avg_lawyer_return ?? 0, 1),
        ]);
    }
}
