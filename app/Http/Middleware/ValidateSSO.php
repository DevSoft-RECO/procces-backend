<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ValidateSSO
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token requerido'], 401);
        }

        try {
            // Cargar la llave pública almacenada localmente
            $publicKeyPath = storage_path('oauth-public.key');

            if (!file_exists($publicKeyPath)) {
                throw new \Exception("Falta la llave pública oauth-public.key en el servidor hijo");
            }

            $publicKey = file_get_contents($publicKeyPath);
            JWT::$leeway = 60; // Mitigar desincronizaciones de reloj entre servidores

            // 1. Decodificar el Token en memoria de forma local (RS256)
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Acceso Denegado (SSO): ' . $e->getMessage()], 401);
        }

        // 2. Carga rápida pasiva desde la base de datos local
        // En SADEC, users.id es igual al ID de la Madre ($decoded->sub)
        $dbUser = User::where('id', $decoded->sub)->first();

        if ($dbUser) {
            // Red de seguridad y auto-reparación: si por alguna razón los roles o permisos están vacíos o nulos en la base de datos local
            // (por ejemplo, porque el usuario ya existía antes de la migración de SSO y las nuevas columnas se crearon vacías),
            // los recuperamos del token criptográficamente verificado y los guardamos físicamente en la base de datos
            // para actualizar de forma permanente el registro del usuario en la primera llamada de API.
            $needsSave = false;

            if ((!is_array($dbUser->roles_list) || empty($dbUser->roles_list) || $dbUser->roles_list === 'Array' || (is_array($dbUser->roles_list) && count($dbUser->roles_list) === 1 && $dbUser->roles_list[0] === 'Array')) && !empty($decoded->roles)) {
                $dbUser->roles_list = is_array($decoded->roles) ? $decoded->roles : [$decoded->roles];
                $needsSave = true;
            }
            if ((!is_array($dbUser->permissions_list) || empty($dbUser->permissions_list) || $dbUser->permissions_list === 'Array' || (is_array($dbUser->permissions_list) && count($dbUser->permissions_list) === 1 && $dbUser->permissions_list[0] === 'Array')) && !empty($decoded->permissions)) {
                $dbUser->permissions_list = is_array($decoded->permissions) ? $decoded->permissions : [$decoded->permissions];
                $needsSave = true;
            }

            if ($needsSave) {
                try {
                    $dbUser->save();
                } catch (\Exception $dbEx) {
                    // Si el guardado falla por base de datos o migración pendiente en producción,
                    // registramos el log de error pero no bloqueamos el inicio de sesión.
                    \Illuminate\Support\Facades\Log::error("Error auto-guardando roles/permisos JIT: " . $dbEx->getMessage());
                }
            }

            // Loguear usuario real de la base de datos
            Auth::setUser($dbUser);
        } else {
            // 3. Fallback de Red de Seguridad (Usuario no sincronizado en DB local aún)
            // Creamos un modelo virtual no persistido con sus roles/permisos del JWT
            $user = new User([
                'id' => $decoded->sub,
                'roles_list' => $decoded->roles ?? [],
                'permissions_list' => $decoded->permissions ?? [],
            ]);
            Auth::setUser($user);
        }

        return $next($request);
    }
}
