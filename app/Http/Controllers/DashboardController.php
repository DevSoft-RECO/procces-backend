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

        $totalActive = NuevoExpediente::whereHas('seguimientos', function($q) {
            $q->where('id_estado', '!=', 11); // Not Finalized
        })->whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth]);

        if ($agencyIds) $totalActive->whereIn('id_agencia', $agencyIds);
        $totalActive = $totalActive->count();

        $totalFinalized = NuevoExpediente::whereHas('seguimientos', function($q) {
            $q->where('id_estado', 11); // Finalized
        })->whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth]);

        if ($agencyIds) $totalFinalized->whereIn('id_agencia', $agencyIds);
        $totalFinalized = $totalFinalized->count();

        // Monto: Filtrado por el mes

        // Monto: Filtrado por el mes
        $amountQuery = NuevoExpediente::whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth]);
        if ($agencyIds) $amountQuery->whereIn('id_agencia', $agencyIds);
        $totalAmount = $amountQuery->sum('monto_documento');

        // Avg Time from Opening to Closing (Finalized Cases)
        // Calculamos el promedio de días que tomó cerrar los expedientes (diferencia entre f_almacenado_admin y fecha_inicio)
        $avgDaysQuery = DB::table('nuevos_expedientes')
            ->join('seguimiento_expedientes', 'nuevos_expedientes.id', '=', 'seguimiento_expedientes.id_expediente')
            ->join('seguimiento_fechas', 'nuevos_expedientes.id', '=', 'seguimiento_fechas.id_expediente')
            ->whereBetween('nuevos_expedientes.fecha_inicio', [$startOfMonth, $endOfMonth])
            ->where('seguimiento_expedientes.id_estado', 11)
            ->whereNotNull('seguimiento_fechas.f_almacenado_admin')
            ->whereNotNull('nuevos_expedientes.fecha_inicio');

        if ($agencyIds) {
            $avgDaysQuery->whereIn('nuevos_expedientes.id_agencia', $agencyIds);
        }

        $avgDaysOpen = $avgDaysQuery
            ->select(DB::raw('AVG(DATEDIFF(seguimiento_fechas.f_almacenado_admin, nuevos_expedientes.fecha_inicio)) as avg_days'))
            ->value('avg_days');

        return response()->json([
            'total_active' => $totalActive,
            'total_finalized' => $totalFinalized,
            'total_amount' => $totalAmount,
            'avg_days_open' => round($avgDaysOpen, 1) // Days passed in current month implicitly covered by avg calculation or strict filter? User asked "days elapsed this month" or "total amount moved this month". Adjusted Amount only as per common KPI needs.
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

        $query = NuevoExpediente::with('asesor')
                ->select('usuario_asesor')
                ->whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth])
                ->whereNotNull('usuario_asesor')
                ->groupBy('usuario_asesor');

        if ($agencyIds) {
            $query->whereIn('id_agencia', $agencyIds);
        }

        $allAdvisors = $query->get();

        $metrics = [];

        foreach ($allAdvisors as $record) {
            $advisorName = $record->asesor->name ?? $record->usuario_asesor ?? 'Unknown';
            $advisorId = $record->usuario_asesor;

            // Common query part
            $baseQuery = NuevoExpediente::where('usuario_asesor', $advisorId);
            if ($agencyIds) {
                $baseQuery->whereIn('id_agencia', $agencyIds);
            }

            $total = (clone $baseQuery)->count();

            $active = (clone $baseQuery)
                ->whereHas('seguimientos', function($q) { $q->where('id_estado', '!=', 11); })
                ->count();

            $rejectedCount = (clone $baseQuery)
                ->whereHas('fechas', function($q) { $q->whereNotNull('f_retorno_asesores'); })
                ->count();

            $creditos = (clone $baseQuery)
                ->whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth])
                ->sum('monto_documento');

             // Rejection Rate
            $rate = $total > 0 ? round(($rejectedCount / $total) * 100, 1) : 0;

            // Success Rate (Clean Cases / Total)
            $cleanCount = $total - $rejectedCount;
            $successRate = $total > 0 ? round(($cleanCount / $total) * 100, 1) : 0;

            $metrics[] = [
                'asesor' => $advisorName,
                'advisor_id' => $advisorId,
                'active_cases' => $active,
                'rejected_cases' => $rejectedCount,
                'total_cases' => $total,
                'rejection_rate' => $rate,
                'success_rate' => $successRate,
                'clean_cases' => $cleanCount,
                'creditos' => $creditos ?? 0
            ];
        }

        // Sort by active cases descending
        usort($metrics, function($a, $b) {
            return $b['active_cases'] <=> $a['active_cases'];
        });

        // Pagination
        $page = $request->input('page', 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $items = array_slice($metrics, $offset, $perPage);
        $totalItems = count($metrics);

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => $perPage,
            'total' => $totalItems,
            'last_page' => ceil($totalItems / $perPage)
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
            ->whereHas('fechas', function($q) {
                $q->whereNotNull('f_retorno_asesores');
            })
            ->select('usuario_asesor', 'id_agencia', DB::raw('count(*) as count'))
            ->groupBy('usuario_asesor', 'id_agencia')
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

        // If locked to a single agency, calculate only for that agency.
        if ($agencyIds) {
            $agencies = \App\Models\Agencia::whereIn('id', $agencyIds)->get();
        } else {
            $agencies = \App\Models\Agencia::all();
        }

        $data = [];

        foreach ($agencies as $agency) {
            $total = NuevoExpediente::where('id_agencia', $agency->id)->count();

            $active = NuevoExpediente::where('id_agencia', $agency->id)
                ->whereHas('seguimientos', function($q) { $q->where('id_estado', '!=', 11); })
                ->count();

            // Rejected at least once
            $rejectedCount = NuevoExpediente::where('id_agencia', $agency->id)
                ->whereHas('fechas', function($q){
                        $q->whereNotNull('f_retorno_asesores');
                })
                ->count();

            $creditos = NuevoExpediente::where('id_agencia', $agency->id)
                ->whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth])
                ->sum('monto_documento');

            // Rejection Rate
            $rate = $total > 0 ? round(($rejectedCount / $total) * 100, 1) : 0;

            // Success Rate (Clean Cases / Total)
            $cleanCount = $total - $rejectedCount;
            $successRate = $total > 0 ? round(($cleanCount / $total) * 100, 1) : 0;

            $data[] = [
                'agency' => $agency->nombre,
                'active' => $active,
                'rejected_cases' => $rejectedCount,
                'total' => $total,
                'rejection_rate' => $rate,
                'success_rate' => $successRate,
                'creditos' => $creditos ?? 0
            ];
        }

        // Sort by active cases descending
        usort($data, function($a, $b) {
            return $b['active'] <=> $a['active'];
        });

        // Pagination
        $page = $request->input('page', 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $items = array_slice($data, $offset, $perPage);
        $total = count($data);

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ]);
    }

    /**
     * Trends (Last 6 Months)
     */
    public function trends(Request $request)
    {
        $agencyIds = $this->getAuthorizedAgencyId($request);
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabel = $date->format('Y-m');
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            // Created
            $createdQuery = NuevoExpediente::whereBetween('fecha_inicio', [$start, $end]);
            if ($agencyIds) $createdQuery->whereIn('id_agencia', $agencyIds);
            $created = $createdQuery->count();

            // Finalized (Based on archivado_at in seguimientos or f_almacenado_admin)
            $finalizedQuery = SeguimientoFecha::whereBetween('f_almacenado_admin', [$start, $end]);
            if ($agencyIds) {
                // To filter SeguimientoFecha by agency requires join with nuevos_expedientes
                $finalizedQuery = DB::table('seguimiento_fechas')
                    ->join('nuevos_expedientes', 'seguimiento_fechas.id_expediente', '=', 'nuevos_expedientes.id')
                    ->whereIn('nuevos_expedientes.id_agencia', $agencyIds)
                    ->whereBetween('seguimiento_fechas.f_almacenado_admin', [$start, $end]);
            }
            $finalized = $finalizedQuery->count();

            $months[] = [
                'month' => $monthLabel,
                'created' => $created,
                'finalized' => $finalized
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
