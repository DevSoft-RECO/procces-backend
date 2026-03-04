<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CancelarCreditoController extends Controller
{
    /**
     * Buscar expediente activo por número de producto O código de cliente.
     */
    public function search(Request $request)
    {
        $termino = trim($request->input('termino'));

        if (!$termino) {
            return response()->json(['message' => 'Término de búsqueda requerido'], 400);
        }

        $expediente = NuevoExpediente::with(['agencia', 'asesor', 'documentos.tipoDocumento', 'documentos.registroPropiedad'])
            ->where(function($q) use ($termino) {
                $q->where('numero_documento', $termino)
                  ->orWhere('codigo_cliente', $termino);
            })
            ->withCount('documentos')
            ->orderByDesc('documentos_count')
            ->latest()
            ->first();

        if (!$expediente) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontró ningún expediente con ese número.'
            ]);
        }

        if ($expediente->documentos->isNotEmpty()) {
            $estadoGeneral = $expediente->documentos->first()->pivot->estado ?? 'activo';
            $expediente->estado = $estadoGeneral;

            $documentosLimpios = $expediente->documentos->map(function($doc) {
                return (object)[
                    'id' => $doc->id,
                    'numero' => $doc->numero,
                    'fecha' => $doc->fecha,
                    'propietario' => $doc->propietario,
                    'tipo_documento_id' => $doc->tipo_documento_id,
                    'tipo_documento_nombre' => $doc->tipoDocumento->nombre ?? 'Desconocido',
                    'estado_pivote' => $doc->pivot->estado ?? 'activo',
                ];
            });

            // Reemplazamos la colección cruda por la colección limpia
            unset($expediente->documentos);
            $expediente->documentos_filtrados = $documentosLimpios;
            $expediente->tiene_seguimiento = true;

        } else {
            unset($expediente->documentos);
            $expediente->documentos_filtrados = [];
            $expediente->estado = 'sin_seguimiento';
            $expediente->tiene_seguimiento = false;
        }

        // Limpiamos los datos base del expediente de relaciones anidadas innecesarias para la vista
        $dataLimpia = [
            'id' => $expediente->id,
            'numero_documento' => $expediente->numero_documento,
            'codigo_cliente' => $expediente->codigo_cliente,
            'nombre_asociado' => $expediente->nombre_asociado,
            'tipo_credito' => $expediente->tipo_garantia,
            'monto_original' => $expediente->monto_documento,
            'fecha_otorgado' => $expediente->fecha_inicio ? $expediente->fecha_inicio->format('Y-m-d') : null,
            'estado' => $expediente->estado,
            'tiene_seguimiento' => $expediente->tiene_seguimiento,
            'agencia' => $expediente->agencia->nombre ?? 'N/A',
            'asesor' => $expediente->usuario_asesor ?? 'N/A',
            'documentos' => $expediente->documentos_filtrados
        ];

        return response()->json([
            'found' => true,
            'data' => $dataLimpia
        ]);
    }

    /**
     * Alternar el estado de un expediente (activo <-> cancelado)
     */
    public function toggleStatus(Request $request, $id)
    {
        // Add basic validation for role safety here if not done in central middleware
        // Only checking if permission exists. We assume permission check happens on API guard or Vue client side
        $user = Auth::user();
        $roles = $user->roles_list ?? [];
        $permissions = $user->permissions_list ?? [];

        // Simple validation layer (though typically handled by Spatie Middleware if set up)
        if (!in_array('Super Admin', $roles) && !in_array('cancelar_creditos', $permissions)) {
             return response()->json(['message' => 'No tiene permisos para realizar esta acción.'], 403);
        }

        $expediente = NuevoExpediente::with('documentos')->find($id);

        if (!$expediente || $expediente->documentos->isEmpty()) {
            return response()->json(['message' => 'Expediente no encontrado o sin seguimiento.'], 404);
        }

        $estadoActualPivote = $expediente->documentos->first()->pivot->estado ?? 'activo';
        $nuevoEstado = ($estadoActualPivote === 'activo') ? 'cancelado' : 'activo';

        // Actualizamos explícitamente solo en la tabla pivote
        \Illuminate\Support\Facades\DB::table('documento_nuevo_expediente')
            ->where('nuevo_expediente_id', $expediente->id)
            ->update(['estado' => $nuevoEstado]);

        // Opcional: Si deseas que el expediente en sí también cambie (descomentar si es requerido)
        // $expediente->estado = $nuevoEstado;
        // $expediente->save();

        // Registrar acción en Logs o tracking
        Log::info("El usuario {$user->id} ({$user->name}) cambió el estado PIVOTE del expediente {$expediente->numero_documento} a {$nuevoEstado}.");

        return response()->json([
            'message' => "El estado del expediente ha cambiado exitosamente a {$nuevoEstado}.",
            'estado' => $nuevoEstado
        ]);
    }
}
