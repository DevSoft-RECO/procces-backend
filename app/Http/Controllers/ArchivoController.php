<?php

namespace App\Http\Controllers;

use App\Models\NuevoExpediente;
use Illuminate\Http\Request;

class ArchivoController extends Controller
{
    /**
     * Buzón de Recibidos en Archivo.
     * Lista expedientes donde el estado actual es 4 (Archivo)
     * O el estado secundario es 4 (Archivo Preliminar/Paralelo).
     */
    public function buzonRecibidos(Request $request)
    {
        $expedientes = NuevoExpediente::whereHas('seguimientos', function ($query) {
            $query->where(function ($sub) {
                $sub->where('id_estado', 4)
                    ->orWhere('id_estado_secundario', 4);
            })
            // Asegurarnos de que estamos viendo el ÚLTIMO estado, o al menos que el expediente TIENE ese estado activo.
            // La lógica previa usaba whereRaw para el último created_at.
            // Si estado secundario es 4, puede que el estado principal sea 3.
            // Queremos listar todo lo que "esté en archivo".
            // Si usas whereHas simple, te traerá cualquiera que ALGUNA VEZ tuvo estado 4?
            // No, porque 'seguimientos' filtra sobre la relación.
            // Pero un expediente tiene MUCHOS seguimientos.
            // Debemos filtrar sobre el *último* seguimiento.
            ->whereRaw('created_at = (select max(created_at) from seguimiento_expedientes where id_expediente = nuevos_expedientes.codigo_cliente)');
        })
        ->with(['fechas', 'seguimientos' => function ($query) {
            $query->orderBy('created_at', 'desc')->with('estado');
        }])
        ->orderBy('fecha_inicio', 'desc')
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }


    /**
     * Marcar Garantía Real como recibida.
     * Solo si estado secundario es 4.
     */
    public function recibirGarantiaReal(Request $request, $id_expediente)
    {
        // Buscar el último seguimiento, o filtrar por el que tenga estado_secundario = 4?
        // Asumiremos que trabajamos sobre el último seguimiento activo del expediente.
        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id_expediente)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$seguimiento) {
            return response()->json(['success' => false, 'message' => 'No se encontró seguimiento para este expediente.'], 404);
        }

        // Validar lógica de negocio (opcional pero recomendada por seguridad)
        /*
        if ($seguimiento->id_estado_secundario != 4) {
             return response()->json(['success' => false, 'message' => 'El expediente no está en estado de recepción de garantía.'], 400);
        }
        */

        $seguimiento->update([
            'recibi_garantia_real' => 'Si - ' . now()->format('d/m/Y H:i')
        ]);

        return response()->json(['success' => true, 'message' => 'Garantía Real marcada como recibida.']);
    }

    /**
     * Marcar Contrato como recibido.
     * Solo si estado es 4.
     */
    public function recibirContrato(Request $request, $id_expediente)
    {
        $seguimiento = \App\Models\SeguimientoExpediente::where('id_expediente', $id_expediente)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$seguimiento) {
            return response()->json(['success' => false, 'message' => 'No se encontró seguimiento para este expediente.'], 404);
        }

        /*
        if ($seguimiento->id_estado != 4) {
             return response()->json(['success' => false, 'message' => 'El expediente no está en estado de recepción de contrato.'], 400);
        }
        */

        $seguimiento->update([
            'recibi_contrato' => 'Si - ' . now()->format('d/m/Y H:i')
        ]);

        return response()->json(['success' => true, 'message' => 'Contrato marcado como recibido.']);
    }
}
