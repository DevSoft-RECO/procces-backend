<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MotherAppService
{
    protected $baseUrl;

    public function __construct() {
        $this->baseUrl = config('services.app_madre.url');
    }

    public function getUserFromToken($token)
    {
        // Usamos Cache para evitar llamar a la Madre en cada request (TTL: 5 min)
        // La llave del caché es un hash del token
        return Cache::remember("sso_user_" . sha1($token), 300, function () use ($token) {

            $response = Http::withToken($token)
                ->acceptJson()
                ->get($this->baseUrl . '/api/user');

            if ($response->failed()) {
                return null; // Token inválido o expirado
            }

            return $response->json();
        });
    }

    public function getAgencias($token)
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->baseUrl . '/api/agencias');

        if ($response->failed()) {
            throw new \Exception("Error al obtener agencias de la App Madre: " . $response->status());
        }

        return $response->json();
    }
}
