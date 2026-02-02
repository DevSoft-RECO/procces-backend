<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use App\Services\MotherAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgenciaController extends Controller
{
    protected $motherService;

    public function __construct(MotherAppService $service)
    {
        $this->motherService = $service;
    }

    /**
     * Display a listing of the resource (Local).
     */
    public function index()
    {
        return Agencia::all();
    }

    /**
     * Obtiene agencias de Madre y las combina con locales.
     */
    public function previewSync(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            // 1. Obtener de Madre
            $agenciasMadre = $this->motherService->getAgencias($token);
            // Ajustar si la respuesta viene envuelta en 'data'
            $dataMadre = $agenciasMadre['data'] ?? $agenciasMadre;

            // 2. Obtener Locales (mapeadas por ID)
            $agenciasLocales = Agencia::all()->keyBy('id');

            // 3. Combinar
            $resultado = collect($dataMadre)->map(function($item) use ($agenciasLocales) {
                // El item de Madre trae 'id', 'nombre', 'codigo'
                $id = $item['id'];

                // Buscar si existe local
                $local = $agenciasLocales->get($id);

                return [
                    'id' => $id,
                    'nombre' => $item['nombre'],
                    'codigo_madre' => $item['codigo'], // Codigo original
                    'is_synced' => !!$local,
                ];
            });

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda la sincronización (lista Masiva).
     */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'agencias' => 'required|array',
            'agencias.*.id' => 'required|integer',
            'agencias.*.nombre' => 'required|string',
            'agencias.*.codigo_madre' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            foreach ($data['agencias'] as $row) {
                // Sincronizamos (ID, Nombre, Codigo)
                Agencia::updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'nombre' => $row['nombre'],
                        'codigo' => $row['codigo_madre'],
                    ]
                );
            }
            DB::commit();
            return response()->json(['message' => 'Sincronización completada']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }
}
