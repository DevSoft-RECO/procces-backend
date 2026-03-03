<?php

namespace App\Http\Controllers;

use App\Models\LoteImportacion;
use App\Models\NuevoExpediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchManagementController extends Controller
{
    /**
     * List all import batches.
     */
    public function index()
    {
        $lotes = LoteImportacion::with('usuario:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lotes
        ]);
    }

    /**
     * Show basic information of records in a batch for preview.
     */
    public function show($id)
    {
        $records = NuevoExpediente::where('id_lote', $id)
            ->select('id', 'codigo_cliente', 'nombre_asociado', 'numero_documento', 'created_at')
            ->limit(500) // Safety limit for preview
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * Delete a batch and all associated records.
     */
    public function destroy($id)
    {
        $lote = LoteImportacion::find($id);

        if (!$lote) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Delete associated nuevos_expedientes
            // Using cascade delete if configured in migration, but doing it explicitly here for safety
            NuevoExpediente::where('id_lote', $id)->delete();

            // Delete the batch record
            $lote->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lote y sus registros eliminados correctamente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting batch $id: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el lote: ' . $e->getMessage()
            ], 500);
        }
    }
}
