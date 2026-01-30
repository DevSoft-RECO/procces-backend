<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http; // Added this use statement

// Asegúrate de que el middleware 'sso' esté registrado en bootstrap/app.php
Route::middleware('sso')->group(function () {
    Route::get('/me', function (Request $request) {
        // Opción B: Proxy a la App Madre
        // Como el token no trae datos, le preguntamos a la madre quién es el dueño del token.

        $token = $request->bearerToken();
        $madreUrl = config('services.app_madre.url') ?? env('APP_MADRE_URL');

        try {
            $response = Http::withToken($token) // Changed to use the facade directly
                ->get($madreUrl . '/api/user');

            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['message' => 'Error validando con App Madre'], $response->status());
            }
        } catch (\Exception $e) {
             return response()->json(['message' => 'Error de conexión con App Madre: ' . $e->getMessage()], 500);
        }
    });
});

// Import Routes
Route::post('/import/upload', [App\Http\Controllers\ImportController::class, 'upload']);
Route::get('/import/status/{id}', [App\Http\Controllers\ImportController::class, 'status']);
Route::post('/clientes/search', [App\Http\Controllers\ClienteController::class, 'search']);
