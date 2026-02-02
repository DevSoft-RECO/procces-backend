<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\MotherAppService;
use App\Models\User;

class ValidateSSO
{
    protected $motherService;

    public function __construct(MotherAppService $service) {
        $this->motherService = $service;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            // 1. Obtener datos validados y cacheados de la App Madre
            $userData = $this->motherService->getUserFromToken($token);

            if (!$userData) {
                 return response()->json(['message' => 'Token Inválido o Expirado'], 401);
            }

            // 2. Aprovisionamiento Just-In-Time (Sync local)
            // Sincronizamos los datos básicos en la tabla users local.
            // Usamos updateOrCreate para crear o actualizar (si cambió el nombre en la madre)
            $user = User::updateOrCreate(
                ['id' => $userData['id']], // Buscamos por ID (mismo que madre)
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'username' => $userData['username'] ?? null,
                    'telefono' => $userData['telefono'] ?? null,
                    // timestamps se manejan solos
                ]
            );

            // 3. Inyectar Roles y Permisos (Transitorio, no BD)
            // Estos vienen frescos del token/servicio y se usan para gates/policies en este request
            $user->roles_list = $userData['roles'] ?? [];
            $user->permissions_list = $userData['permissions'] ?? [];
            $user->agencia_data = $userData['agencia'] ?? null;

            // 4. Loguear al usuario en Laravel (Auth Facade)
            // Esto permite usar auth()->user() o $request->user() en controladores
            Auth::login($user);

            return $next($request);

        } catch (\Throwable $e) {
            // Capturamos cualquier error (incluyendo clases no encontradas o DB errors)
            // Retornamos 401 o 500 según corresponda, pero JSON limpio.
            return response()->json(['message' => 'SSO Error: ' . $e->getMessage()], 401);
        }
    }
}
