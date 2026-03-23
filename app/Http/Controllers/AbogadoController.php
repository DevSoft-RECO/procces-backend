<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;

class AbogadoController extends Controller
{
    /**
     * List expedientes in "En manos de abogado" (State 8) or "Recibido por abogado" (State 9).
     */
    public function buzon(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        // Fetch expedientes where the *latest* tracking status is 8 or 9
        $query = NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->whereIn('id_estado', [8, 9])
                  ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.id)');
        });

        // ROLE RESTRICTIONS: If not Super Admin, only show expedientes assigned to their bufete
        if (!$isSuperAdmin) {
            $bufete = \App\Models\Bufete::where('user_id', $user->id)->first();
            if (!$bufete) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $query->whereHas('seguimientos', function ($q) use ($bufete) {
                $q->where('bufete_id', $bufete->id);
            });
        }

        $expedientes = $query->with(['seguimientos' => function ($query) {
            $query->orderBy('created_at', 'desc')->with(['estado', 'bufete.user', 'bufete.agencia']);
        }, 'fechas', 'agencia'])
        ->get();

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Mark expedientes as received by the lawyer.
     * Updates existing record (state 8 -> 9) and sets timestamp.
     */
    public function recibir(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nuevos_expedientes,id',
        ]);

        $id = $request->id;

        // 1. Update Tracking State (seguimiento_expedientes)
        // Find the latest tracking record (which should be state 8)
        $ultimoSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($ultimoSeguimiento && $ultimoSeguimiento->id_estado == 8) {
            $ultimoSeguimiento->id_estado = 9; // Change to state 9 (Recibido/Revisión)
            $ultimoSeguimiento->save();
        }

        // 2. Update Dates (seguimiento_fechas)
        // Find or create the dates record
        $fechas = \App\Models\SeguimientoFecha::firstOrCreate(
            ['id_expediente' => $id]
        );

        // Update the accepted date if not already set
        if (!$fechas->f_aceptado_abogado) {
            $fechas->f_aceptado_abogado = now();
            $fechas->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Expediente marcado como recibido correctamente.',
            'data' => $fechas
        ]);
    }

    /**
     * Send expediente back to Secretaria de Creditos (State 10).
     */
    public function enviarSecretaria(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:nuevos_expedientes,id',
        ]);

        $id = $request->id;

        // 1. Update Tracking State (seguimiento_expedientes)
        $ultimoSeguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($ultimoSeguimiento) {
            $ultimoSeguimiento->id_estado = 10; // Devolución a Secretaría
            $ultimoSeguimiento->save();
        }

        // 2. Update Dates (seguimiento_fechas)
        $fechas = \App\Models\SeguimientoFecha::firstOrCreate(
            ['id_expediente' => $id]
        );

        if (!$fechas->f_enviado_secretaria_credito) {
            $fechas->f_enviado_secretaria_credito = now();
            $fechas->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Expediente enviado a Secretaría de Créditos correctamente.',
        ]);
    }

    /**
     * List expedientes returned to Secretaria (State 10).
     */

    public function devueltos(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = NuevoExpediente::select([
                'nuevos_expedientes.id',
                'nuevos_expedientes.id_agencia',
                'nuevos_expedientes.codigo_cliente',
                'nuevos_expedientes.nombre_asociado',
                'nuevos_expedientes.numero_documento'
            ])
            ->with([
                'fechas:id_expediente,f_enviado_secretaria_credito',
                'agencia',
                'seguimientos' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ]);

        // FILTER: Current state must be 10 (Devolución a Secretaría)
        $query->whereHas('seguimientos', function ($q) {
            $q->where('id_estado', 10)
              ->whereRaw('id_seguimiento = (select max(id_seguimiento) from seguimiento_expedientes where id_expediente = nuevos_expedientes.id)');
        });

        // ROLE RESTRICTIONS: If not Super Admin, only show expedientes assigned to their bufete
        if (!$isSuperAdmin) {
            $bufete = \App\Models\Bufete::where('user_id', $user->id)->first();
            if (!$bufete) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => [],
                        'current_page' => 1,
                        'last_page' => 1,
                        'total' => 0
                    ]
                ]);
            }
            
            $query->whereHas('seguimientos', function ($q) use ($bufete) {
                $q->where('bufete_id', $bufete->id);
            });
        }

        // DATE FILTER (on f_enviado_secretaria_credito)
        if ($fechaInicio && $fechaFin) {
            $query->whereHas('fechas', function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('f_enviado_secretaria_credito', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);
            });
        }

        $expedientes = $query->latest('nuevos_expedientes.id')->paginate(15);

        return response()->json($expedientes);
    }

    public function exportarDevueltosCSV(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $query = NuevoExpediente::select([
                'nuevos_expedientes.id',
                'nuevos_expedientes.codigo_cliente',
                'nuevos_expedientes.nombre_asociado',
                'nuevos_expedientes.numero_documento'
            ])
            ->with(['fechas:id_expediente,f_enviado_secretaria_credito']);

        // FILTER: Current state must be 10
        $query->whereHas('seguimientos', function ($q) {
            $q->where('id_estado', 10)
              ->whereRaw('id_seguimiento = (select max(id_seguimiento) from seguimiento_expedientes where id_expediente = nuevos_expedientes.id)');
        });

        if (!$isSuperAdmin) {
            $bufete = \App\Models\Bufete::where('user_id', $user->id)->first();
            if (!$bufete) {
                return response()->json(['success' => false, 'message' => 'No se encontró bufete asociado.']);
            }
            $query->whereHas('seguimientos', function ($q) use ($bufete) {
                $q->where('bufete_id', $bufete->id);
            });
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereHas('fechas', function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('f_enviado_secretaria_credito', [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59']);
            });
        }

        $expedientes = $query->latest('nuevos_expedientes.id')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="expedientes_devueltos.csv"',
        ];

        $callback = function () use ($expedientes) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Código Cliente', 'Nombre Asociado', 'Número Documento', 'Fecha Envío Secretaría']);

            foreach ($expedientes as $exp) {
                fputcsv($file, [
                    $exp->id,
                    $exp->codigo_cliente,
                    $exp->nombre_asociado,
                    $exp->numero_documento,
                    $exp->fechas?->f_enviado_secretaria_credito ? $exp->fechas->f_enviado_secretaria_credito->format('d/m/Y H:i') : 'N/A'
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

}
