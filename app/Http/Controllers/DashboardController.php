<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\SeguimientoFecha;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get High-Level KPIs
     */
    public function kpi()
    {
        $totalActive = NuevoExpediente::whereHas('seguimientos', function($q) {
            $q->where('id_estado', '!=', 11); // Not Finalized
        })->count();

        $totalFinalized = NuevoExpediente::whereHas('seguimientos', function($q) {
            $q->where('id_estado', 11); // Finalized
        })->count();

        // Monto: Filtrado por el mes actual (Created/Started this month)
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $totalAmount = NuevoExpediente::whereBetween('fecha_inicio', [$startOfMonth, $endOfMonth])
            ->sum('monto_documento');

        // Avg Time from Opening to Closing (Finalized Cases)
        // Calculamos el promedio de días que tomó cerrar los expedientes (diferencia entre f_almacenado_admin y fecha_inicio)
        $avgDaysOpen = DB::table('nuevos_expedientes')
            ->join('seguimiento_expedientes', 'nuevos_expedientes.id', '=', 'seguimiento_expedientes.id_expediente')
            ->join('seguimiento_fechas', 'nuevos_expedientes.id', '=', 'seguimiento_fechas.id_expediente')
            ->where('seguimiento_expedientes.id_estado', 11)
            ->whereNotNull('seguimiento_fechas.f_almacenado_admin')
            ->whereNotNull('nuevos_expedientes.fecha_inicio')
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
    public function pipeline()
    {
        // Group by Current State
        $distribution = NuevoExpediente::join('seguimiento_expedientes', 'nuevos_expedientes.id', '=', 'seguimiento_expedientes.id_expediente')
            ->join('tipo_estados', 'seguimiento_expedientes.id_estado', '=', 'tipo_estados.id')
            ->select('tipo_estados.nombre as state_name', DB::raw('count(*) as count'))
            ->where('seguimiento_expedientes.id_estado', '!=', 11) // Exclude Finalized for pipeline view
            ->groupBy('tipo_estados.nombre')
            ->orderBy('count', 'desc')
            ->get();

        return response()->json($distribution);
    }

    /**
     * Get Advisor Performance (Paginated)
     */
    public function advisors(Request $request)
    {
        $query = NuevoExpediente::with('asesor')
            ->select('usuario_asesor')
            ->whereNotNull('usuario_asesor')
            ->groupBy('usuario_asesor');

        if ($request->has('agency_id') && $request->agency_id) {
            $query->where('id_agencia', $request->agency_id);
        }

        $allAdvisors = $query->get();
        // ... rest of logic needs to be careful because we iterate $allAdvisors but metrics calculation also needs to respect the filter?
        // Actually, $allAdvisors gives us the list of advisors.
        // Then inside the loop we calculate metrics for each advisor.
        // The metrics should likely be for ALL their cases, OR just cases within that agency?
        // "Rendimiento de asesores por agencia": This usually means "Show me advisors belonging to this agency and their performance".
        // If an advisor works for multiple agencies (unlikely in this domain?), we might want to filter metrics too.
        // But usually an advisor is tied to an agency or the cases are.
        // Let's assume we filter the *list* of advisors based on whether they have cases in that agency.
        // And the metrics? "Active cases" -> should probably be filtered by agency too if we are looking at "Agency Performance".
        // Users usually want "How is this advisor performing *in this agency*?".
        // So I should add filtering to the metrics queries too.

        // Refactored approach:
        $agencyId = $request->input('agency_id');

        $metrics = [];

        foreach ($allAdvisors as $record) {
            $advisorName = $record->asesor->name ?? $record->usuario_asesor ?? 'Unknown';
            $advisorId = $record->usuario_asesor;

            // Common query part
            $baseQuery = NuevoExpediente::where('usuario_asesor', $advisorId);
            if ($agencyId) {
                $baseQuery->where('id_agencia', $agencyId);
            }

            $total = (clone $baseQuery)->count();

            $active = (clone $baseQuery)
                ->whereHas('seguimientos', function($q) { $q->where('id_estado', '!=', 11); })
                ->count();

            $rejectedCount = (clone $baseQuery)
                ->whereHas('fechas', function($q) { $q->whereNotNull('f_retorno_asesores'); })
                ->count();

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
                'clean_cases' => $cleanCount
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
        $agencies = \App\Models\Agencia::all();
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
                'success_rate' => $successRate
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
    public function trends()
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabel = $date->format('Y-m');
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            // Created
            $created = NuevoExpediente::whereBetween('fecha_inicio', [$start, $end])->count();

            // Finalized (Based on archivado_at in seguimientos or f_almacenado_admin)
            // Ideally we use timestamp of status change.
            // We'll use seguimiento_fechas.f_almacenado_admin as proxy for "Done"
            $finalized = SeguimientoFecha::whereBetween('f_almacenado_admin', [$start, $end])->count();

            $months[] = [
                'month' => $monthLabel,
                'created' => $created,
                'finalized' => $finalized
            ];
        }

        return response()->json($months);
    }
    /**
     * Get Average Processing Times (Bottleneck Analysis)
     */
    public function processingTimes()
    {
        // We need to calculate the average time difference between specific dates.
        // 1. Creation -> Tracking (created_at -> f_enviado_secretaria)
        // 2. Secretaria Internal (f_aceptado_secretaria -> f_enviado_protocolos)
        // 3. Secretaria -> Abogado (f_aceptado_secretaria_credito -> f_enviado_abogado)
        // 4. Abogado Processing (f_aceptado_abogado -> f_enviado_secretaria_credito)

        // We use a raw query because doing this in Eloquent collection for all records is heavy.
        // DATEDIFF in MySQL returns days.
        // We only consider records where BOTH dates exist.

        $avgs = DB::table('nuevos_expedientes')
            ->join('seguimiento_fechas', 'nuevos_expedientes.id', '=', 'seguimiento_fechas.id_expediente')
            ->select(
                // 1. Creation -> Sent to Secretary
                DB::raw('AVG(DATEDIFF(seguimiento_fechas.f_enviado_secretaria, nuevos_expedientes.created_at)) as avg_creation_to_secretary'),

                // 2. Secretary Internal (Accepted -> Sent to Protocol)
                // Note: User asked: "f_aceptado_secretaria con f_enviado_protocolos"
                DB::raw('AVG(DATEDIFF(seguimiento_fechas.f_enviado_protocolos, seguimiento_fechas.f_aceptado_secretaria)) as avg_secretary_internal'),

                // 3. Secretary -> Lawyer (Accepted Credit Sec -> Sent to Lawyer)
                // Note: User asked: "f_aceptado_secretaria_creditos y f_enviado_abogado"
                // Column in model/DB is `f_aceptado_secretaria_credito` (singular/plural check: model says singular)
                DB::raw('AVG(DATEDIFF(seguimiento_fechas.f_enviado_abogado, seguimiento_fechas.f_aceptado_secretaria_credito)) as avg_secretary_to_lawyer'),

                // 4. Lawyer Return (Accepted Lawyer -> Sent to Credit Sec)
                // Note: User asked: "f_aceptado_abogado y f_enviado_secretaria_credito"
                DB::raw('AVG(DATEDIFF(seguimiento_fechas.f_enviado_secretaria_credito, seguimiento_fechas.f_aceptado_abogado)) as avg_lawyer_return')
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
