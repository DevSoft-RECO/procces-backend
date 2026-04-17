<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\SeguimientoExpediente;
use App\Models\SeguimientoFecha;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EditarSeguimientoController extends Controller
{
    protected $disk = 'gcs';
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
            $seguimientoData = is_string($request->input('seguimiento')) 
                ? json_decode($request->input('seguimiento'), true) 
                : $request->input('seguimiento', []);

            // Convertir strings vacíos a null para evitar errores en DB
            $seguimientoData = array_map(fn($value) => $value === '' ? null : $value, $seguimientoData);

            // Manejo de Archivo si se proporciona
            if ($request->hasFile('file_contrato')) {
                $file = $request->file('file_contrato');
                
                // Buscar seguimiento actual para eliminar archivo viejo
                $existingSeguimiento = SeguimientoExpediente::where('id_expediente', $id)->first();
                if ($existingSeguimiento && $existingSeguimiento->path_contrato) {
                    if (Storage::disk($this->disk)->exists($existingSeguimiento->path_contrato)) {
                        Storage::disk($this->disk)->delete($existingSeguimiento->path_contrato);
                    }
                }

                // Nomenclatura Estándar: CTO-numero_documento.ext
                $extension = $file->getClientOriginalExtension();
                $numDoc = $expediente->numero_documento ?? 'S-N';
                $filename = "CTO-{$numDoc}.{$extension}";

                $folder = 'sadec/expedientes/contratos_escaneados';
                $path = Storage::disk($this->disk)->putFileAs($folder, $file, $filename);

                $seguimientoData['path_contrato'] = $path;
            }

            SeguimientoExpediente::updateOrCreate(
                ['id_expediente' => $id],
                $seguimientoData
            );

            // 2. Actualizar SeguimientoFecha
            $fechasData = is_string($request->input('fechas'))
                ? json_decode($request->input('fechas'), true)
                : $request->input('fechas', []);

            // Convertir strings vacíos a null para evitar errores en DB (ej. fechas vacías)
            $fechasData = array_map(fn($value) => $value === '' ? null : $value, $fechasData);

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
