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

        // Avg Days Open (Active)
        // This is an approximation. Ideally done in DB, but for now PHP is fine for reasonable dataset.
        // For strict SQL handling we would use DATEDIFF.
        $avgDaysOpen = NuevoExpediente::whereHas('seguimientos', function($q) {
             $q->where('id_estado', '!=', 11);
        })
        ->select(DB::raw('AVG(DATEDIFF(NOW(), fecha_inicio)) as avg_days'))
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
        // 1. Valid Advisors (those with assigned cases)
        $advisors = NuevoExpediente::select('usuario_asesor')
            ->whereNotNull('usuario_asesor')
            ->distinct()
            ->pluck('usuario_asesor');

        $metrics = [];

        foreach ($advisors as $advisor) {
            // Active Cases count
            $active = NuevoExpediente::where('usuario_asesor', $advisor)
                ->whereHas('seguimientos', function($q){
                    $q->where('id_estado', '!=', 11);
                })
                ->count();

            // Historical Rejections (using f_retorno_asesores as flag)
            // A file is considered "Rejected at least once" if it has a f_retorno_asesores date
            $rejectedCount = NuevoExpediente::where('usuario_asesor', $advisor)
                ->whereHas('fechas', function($q){
                     $q->whereNotNull('f_retorno_asesores');
                })
                ->count();

            // Total Processed (Active + Finalized) for context, optionally
            $total = NuevoExpediente::where('usuario_asesor', $advisor)->count();

            // Rejection Rate
            $rate = $total > 0 ? round(($rejectedCount / $total) * 100, 1) : 0;

            $metrics[] = [
                'asesor' => $advisor,
                'active_cases' => $active,
                'rejected_cases' => $rejectedCount,
                'total_cases' => $total,
                'rejection_rate' => $rate
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
        $total = count($metrics);

        return response()->json([
            'data' => $items,
            'current_page' => (int)$page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ]);
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
    public function agencies()
    {
        $agencies = \App\Models\Agencia::all();
        $data = [];

        foreach ($agencies as $agency) {
            $active = NuevoExpediente::where('id_agencia', $agency->id)
                ->whereHas('seguimientos', function($q) { $q->where('id_estado', '!=', 11); })
                ->count();

            $finalized = NuevoExpediente::where('id_agencia', $agency->id)
                ->whereHas('seguimientos', function($q) { $q->where('id_estado', 11); })
                ->count();

            $data[] = [
                'agency' => $agency->nombre,
                'active' => $active,
                'finalized' => $finalized,
                'total' => $active + $finalized
            ];
        }

        // Sort by volume
        usort($data, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return response()->json($data);
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
}
