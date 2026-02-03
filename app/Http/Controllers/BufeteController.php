<?php

namespace App\Http\Controllers;

use App\Models\Bufete;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BufeteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Traemos relación user y agencia para mostrar nombres
        return Bufete::with(['user', 'agencia'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'agencia_id' => 'required|exists:agencias,id',
            'descripcion' => 'nullable|string',
            '_user_data' => 'nullable|array', // Datos para JIT
        ]);

        DB::beginTransaction();
        try {
            $userId = $validated['user_id'];

            // 1. Verificar si el usuario existe localmente
            $localUser = User::find($userId);

            if (!$localUser) {
                // 2. Si no existe por ID, verificar si existe por Email para evitar colisión
                if (!empty($validated['_user_data']['email'])) {
                    $localUser = User::where('email', $validated['_user_data']['email'])->first();
                }

                if (!$localUser) {
                    // 2b. Crear JIT si no existe ni por ID ni por Email
                    if (!empty($validated['_user_data'])) {
                        $userData = $validated['_user_data'];

                        // Sanity Check
                        if ($userData['id'] != $userId) {
                            throw new \Exception("ID de usuario no coincide ({$userData['id']} vs $userId).");
                        }

                        User::create([
                            'id' => $userId,
                            'name' => $userData['name'] ?? 'Usuario - ' . $userId,
                            'email' => $userData['email'] ?? null,
                            'username' => $userData['username'] ?? null, // Ya no usamos email como fallback
                            'telefono' => $userData['telefono'] ?? null, // Agregamos telefono
                        ]);
                    } else {
                        throw new \Exception("Usuario no existe localmente y faltan datos para crearlo.");
                    }
                } else {
                   // Si existe por Email pero el ID era diferente?
                   // No podemos cambiar el ID de un registro existente facilmente.
                   // Asumimos que es el mismo usuario y usamos su ID real.
                   $userId = $localUser->id;
                }
            }

            // 3. Crear el registro en Bufetes
            $bufete = Bufete::create([
                'user_id' => $userId,
                'agencia_id' => $validated['agencia_id'],
                'descripcion' => $validated['descripcion'],
            ]);

            DB::commit();
            return response()->json($bufete, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al guardar bufete: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Bufete $bufete)
    {
        return $bufete->load(['user', 'agencia']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bufete $bufete)
    {
        // Similar validación al store, por si cambian el usuario
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'agencia_id' => 'required|exists:agencias,id',
            'descripcion' => 'nullable|string',
            '_user_data' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Lógica JIT simplificada: asumimos que si cambian User, mandan _user_data
            if ($validated['user_id'] != $bufete->user_id) {
                 $userId = $validated['user_id'];
                 if (!User::find($userId)) {
                      if (!empty($validated['_user_data'])) {
                           $userData = $validated['_user_data'];
                           User::create([
                                'id' => $userId,
                                'name' => $userData['name'],
                                'email' => $userData['email'] ?? null,
                           ]);
                      } else {
                           throw new \Exception("Usuario nuevo no existe localmente.");
                      }
                 }
            }

            $bufete->update([
                'user_id' => $validated['user_id'],
                'agencia_id' => $validated['agencia_id'],
                'descripcion' => $validated['descripcion'],
            ]);

            DB::commit();
            return response()->json($bufete);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bufete $bufete)
    {
        $bufete->delete();
        return response()->json(null, 204);
    }
}
