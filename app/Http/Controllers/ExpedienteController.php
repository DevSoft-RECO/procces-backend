<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expediente;

class ExpedienteController extends Controller
{
    /**
     * List expedientes with pagination.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Expediente::query();

        // If NOT Super Admin, filter by username
        if (!$user->hasRole('Super Admin')) {
            $query->where('usuario_asesor', $user->username);
        }

        $expedientes = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $expedientes
        ]);
    }

    /**
     * Search for a client by CUI/DPI.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $request->validate([
            'cui' => 'required|string',
        ]);

        $expediente = Expediente::where('cui', $request->cui)->first();

        if (!$expediente) {
            return response()->json([
                'success' => false,
                'message' => 'Expediente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expediente
        ]);
    }
}
