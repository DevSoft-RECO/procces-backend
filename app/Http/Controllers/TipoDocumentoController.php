<?php

namespace App\Http\Controllers;

use App\Models\TipoDocumento;
use Illuminate\Http\Request;

class TipoDocumentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tipoDocumentos = TipoDocumento::paginate(10);
        return response()->json([
            'success' => true,
            'data' => $tipoDocumentos
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $tipoDocumento = TipoDocumento::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $tipoDocumento,
            'message' => 'Tipo de documento creado correctamente.'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
         $tipoDocumento = TipoDocumento::find($id);
         if (!$tipoDocumento) return response()->json(['message' => 'No encontrado'], 404);
         return response()->json(['success' => true, 'data' => $tipoDocumento]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $tipoDocumento = TipoDocumento::find($id);
        if (!$tipoDocumento) return response()->json(['message' => 'No encontrado'], 404);

        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $tipoDocumento->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $tipoDocumento,
            'message' => 'Tipo de documento actualizado correctamente.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tipoDocumento = TipoDocumento::find($id);
        if (!$tipoDocumento) return response()->json(['message' => 'No encontrado'], 404);

        $tipoDocumento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tipo de documento eliminado correctamente.'
        ]);
    }
}
