<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NuevoExpediente;
use App\Models\Documento;
use Illuminate\Support\Facades\DB;

class DocumentoDesvinculacionController extends Controller
{
    /**
     * Buscar expedientes por numero_documento o codigo_cliente y listar sus documentos asociados.
     */
    public function searchByExpediente(Request $request)
    {
        $request->validate([
            'numero_documento' => 'required|string'
        ]);

        $numero = trim($request->numero_documento);

        // Buscamos coincidencia exacta en número de documento o código de cliente
        $expediente = NuevoExpediente::where('numero_documento', $numero)
            ->orWhere('codigo_cliente', $numero)
            ->with(['documentos.tipoDocumento', 'agencia'])
            ->first();

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el expediente solicitado con el número: ' . $numero
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expediente
        ]);
    }

    /**
     * Desvincular un documento específico de un expediente.
     */
    public function unlink(Request $request)
    {
        $request->validate([
            'nuevo_expediente_id' => 'required|exists:nuevos_expedientes,id',
            'documento_id' => 'required|exists:documentos,id'
        ]);

        try {
            DB::beginTransaction();

            $expediente = NuevoExpediente::findOrFail($request->nuevo_expediente_id);

            // Detach the relationship
            $expediente->documentos()->detach($request->documento_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento desvinculado correctamente del expediente.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al desvincular el documento: ' . $e->getMessage()
            ], 500);
        }
    }
}
