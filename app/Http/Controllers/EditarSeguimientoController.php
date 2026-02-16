<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use App\Models\SeguimientoFecha;
use Illuminate\Support\Facades\DB;

class EditarSeguimientoController extends Controller
{
    /**
     * Buscar expediente por ID o Número de Documento.
     * Carga relaciones de seguimiento y fechas.
     */
    public function search(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['message' => 'Término de búsqueda requerido'], 400);
        }

        $expediente = NuevoExpediente::with(['seguimientos', 'fechas'])
            ->where('id', $query)
            ->orWhere('numero_documento', $query)
            ->first();

        if (!$expediente) {
            return response()->json(['message' => 'Expediente no encontrado'], 404);
        }

        // Transformar relación 'seguimientos' (HasMany) a objeto único para el frontend
        // Si existe colección y tiene al menos uno, tomamos el primero.
        // Si es colección vacía o null, creamos nueva instancia.
        $seguimiento = null;
        if ($expediente->seguimientos && $expediente->seguimientos->count() > 0) {
            $seguimiento = $expediente->seguimientos->first();
        } else {
             $seguimiento = new SeguimientoExpediente(['id_expediente' => $expediente->id]);
        }
        $expediente->setRelation('seguimientos', $seguimiento);

        // Relación 'fechas' es HasOne, pero aseguramos instancia si es null
        if (!$expediente->fechas) {
            $expediente->setRelation('fechas', new SeguimientoFecha(['id_expediente' => $expediente->id]));
        }

        return response()->json([
            'success' => true,
            'data' => $expediente
        ]);
    }

    /**
     * Actualizar información de seguimiento y fechas.
     */
    public function update(Request $request, $id)
    {
        $expediente = NuevoExpediente::findOrFail($id);

        try {
            DB::beginTransaction();

            // 1. Actualizar SeguimientoExpediente
            // Usamos updateOrCreate para asegurar que exista
            $seguimientoData = $request->input('seguimiento', []);

            // Filtrar campos nulos o vacíos si es necesario, o dejar que el frontend mande todo
            // Por seguridad, usar only() con los campos permitidos definidos en el plan

            SeguimientoExpediente::updateOrCreate(
                ['id_expediente' => $id],
                $seguimientoData
            );

            // 2. Actualizar SeguimientoFecha
            $fechasData = $request->input('fechas', []);

            SeguimientoFecha::updateOrCreate(
                ['id_expediente' => $id],
                $fechasData
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Seguimiento actualizado correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar seguimiento: ' . $e->getMessage()
            ], 500);
        }
    }
}
