<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Agencia;
use Illuminate\Http\JsonResponse;

class SSOController extends Controller
{
    /**
     * Sincroniza el perfil JIT (Just-In-Time) con la App Madre.
     * Esta función es el corazón del ecosistema para obtener identidad, roles y permisos.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $motherUrl = config('services.app_madre.url') ?? env('APP_MADRE_URL');

        try {
            // 1. Consultar a la Madre usando el mismo Bearer Token
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("{$motherUrl}/api/me");

            if (!$response->successful()) {
                // Fallback por si en la madre no está implementado /api/me pero sí /api/user
                $response = Http::withToken($token)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get("{$motherUrl}/api/user");
            }

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Fallo en la sincronización con el ecosistema (Madre)',
                    'error' => $response->reason()
                ], 502);
            }

            $userData = $response->json();

            // Desempaquetar si viene en 'data' (Laravel Resources)
            if (isset($userData['data'])) {
                $userData = $userData['data'];
            }

            $username = $userData['username'] ?? 'unknown';

            // 2. APLANAMIENTO Y FILTRADO POR CATEGORÍA "App_SADEC"
            $roles = $this->flatten($userData['roles'] ?? $userData['roles_list'] ?? []);

            // Filtrar permisos por la categoría asignada "App_SADEC" para no almacenar permisos de otras apps
            if (isset($userData['permissions_detailed']) && is_array($userData['permissions_detailed'])) {
                $filteredPermissions = array_filter($userData['permissions_detailed'], function ($perm) {
                    return isset($perm['category']) && $perm['category'] === 'App_SADEC';
                });
                // array_values() es indispensable para resetear los índices del array filtrado
                $permisos = array_values(array_map(function ($perm) {
                    return $perm['name'] ?? '';
                }, $filteredPermissions));

                // Red de seguridad: si el filtrado por categoría dio vacío pero el usuario sí tiene permisos,
                // hacemos un fallback a aplanar toda la lista plana para no dejar al usuario sin accesos en producción.
                if (empty($permisos)) {
                    $permisos = $this->flatten($userData['permisos'] ?? $userData['permissions'] ?? $userData['permissions_list'] ?? []);
                }
            } else {
                $permisos = $this->flatten($userData['permisos'] ?? $userData['permissions'] ?? $userData['permissions_list'] ?? []);
            }

            // 3. Extracción de JTI del Token (para mirroring con Go)
            $jti = null;
            $tokenParts = explode('.', $token);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                $jti = $payload['jti'] ?? null;
            }

            // 4. SINCRONIZACIÓN JIT (Just-In-Time)

            // Sincronizar Agencia local
            if (isset($userData['agencia'])) {
                $agData = $userData['agencia'];
                Agencia::updateOrCreate(
                    ['id' => $agData['id']],
                    [
                        'nombre' => $agData['nombre'],
                        'codigo' => $agData['codigo'] ?? null,
                    ]
                );
            }

            // Buscar usuario local por ID de Madre o por Username (Case-Insensitive)
            $user = User::where('id', $userData['id'])
                ->orWhereRaw('LOWER(username) = ?', [strtolower($username)])
                ->first();

            $updateData = [
                'id'               => $userData['id'], // Mantener ID de la madre como primary key
                'name'             => $userData['name'],
                'email'            => $userData['email'],
                'telefono'         => $userData['telefono'] ?? null,
                'id_agencia'       => $userData['idagencia'] ?? $userData['agencia']['id'] ?? null,
                'puesto'           => $userData['puesto']['nombre'] ?? $userData['puesto'] ?? null,
                'avatar'           => $userData['avatar'] ?? null,
                'roles_list'       => $roles,
                'permissions_list' => $permisos,
                'jti'              => $jti,
            ];

            if ($user) {
                // Si existe localmente, actualizamos los datos
                $user->update($updateData);
            } else {
                // Si es un usuario nuevo, lo creamos
                $updateData['username'] = strtoupper($username);
                $user = User::create($updateData);
            }

            // 5. Fallbacks de estandarización para el Frontend (Evita discrepancias entre BD y sesión del cliente)
            $userData['roles'] = $roles;
            $userData['roles_list'] = $roles;
            $userData['permisos'] = $permisos;
            $userData['permissions'] = $permisos;
            $userData['_source'] = 'madre_sync';

            return response()->json($userData);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno de comunicación SSO',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convierte colecciones de objetos de roles/permisos en arreglos de strings.
     *
     * @param mixed $items
     * @return array
     */
    private function flatten($items): array
    {
        if (!is_array($items)) return [];

        return array_map(function ($item) {
            return is_array($item) ? ($item['name'] ?? $item) : $item;
        }, $items);
    }
}
