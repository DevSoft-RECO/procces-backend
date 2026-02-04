<?php

namespace App\Http\Controllers;

use App\Models\TipoEstado;
use Illuminate\Http\Request;

class TipoEstadoController extends Controller
{
    public function index()
    {
        return TipoEstado::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $tipoEstado = TipoEstado::create($validated);
        return response()->json($tipoEstado, 201);
    }

    public function show($id)
    {
        return TipoEstado::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $tipoEstado = TipoEstado::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $tipoEstado->update($validated);
        return response()->json($tipoEstado);
    }

    public function destroy($id)
    {
        $tipoEstado = TipoEstado::findOrFail($id);
        $tipoEstado->delete();
        return response()->json(null, 204);
    }
}
