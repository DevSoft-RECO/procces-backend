<?php

namespace App\Http\Controllers;

use App\Models\Garantia;
use Illuminate\Http\Request;

class GarantiaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $garantias = Garantia::paginate(10);
        return response()->json([
            'success' => true,
            'data' => $garantias
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

        $garantia = Garantia::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $garantia,
            'message' => 'Garantía creada correctamente.'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
         $garantia = Garantia::find($id);
         if (!$garantia) return response()->json(['message' => 'No encontrado'], 404);
         return response()->json(['success' => true, 'data' => $garantia]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $garantia = Garantia::find($id);
        if (!$garantia) return response()->json(['message' => 'No encontrado'], 404);

        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $garantia->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $garantia,
            'message' => 'Garantía actualizada correctamente.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $garantia = Garantia::find($id);
        if (!$garantia) return response()->json(['message' => 'No encontrado'], 404);

        $garantia->delete();

        return response()->json([
            'success' => true,
            'message' => 'Garantía eliminada correctamente.'
        ]);
    }
}
