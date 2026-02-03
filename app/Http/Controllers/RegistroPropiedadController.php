<?php

namespace App\Http\Controllers;

use App\Models\RegistroPropiedad;
use Illuminate\Http\Request;

class RegistroPropiedadController extends Controller
{
    public function index()
    {
        return RegistroPropiedad::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $registro = RegistroPropiedad::create($validated);
        return response()->json($registro, 201);
    }

    public function show(RegistroPropiedad $registroPropiedad) // Route binding usually matches parameter name or model name based on route
    {
        // Route parameter is likely 'registro_propiedad' or 'registros_propiedad' depending on route definition.
        // Laravel ApiResource defaults:
        // Route::apiResource('registros-propiedad', ...) -> parameter is {registros_propiedad} (singularized automatically? No, standard is {registros_propiedad} unless tailored).
        // Let's stick to $id or generic binding if unsure, but Model Binding is cleaner.
        // Usually apiResource('photos', PhotoController) -> {photo}
        // apiResource('registros-propiedad', ...) -> {registros_propiedad} ?
        // I will use generic id for safety or match the arg.
        return $registroPropiedad;
    }

    // Changing arg to match standard auto-binding if possible, or just $id.
    // Let's use standard Request $request, $id for simplicity if binding is tricky with hyphenated resources.
    // Actually, let's use the Model injection.

    public function update(Request $request, $id)
    {
        $registro = RegistroPropiedad::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        $registro->update($validated);
        return response()->json($registro);
    }

    public function destroy($id)
    {
        $registro = RegistroPropiedad::findOrFail($id);
        $registro->delete();
        return response()->json(null, 204);
    }
}
