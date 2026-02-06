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

    Route::get('/users/search', function (Request $request) {
        $token = $request->bearerToken();
        $madreUrl = config('services.app_madre.url') ?? env('APP_MADRE_URL');
        $query = $request->input('q');

        try {
            $response = Http::withToken($token)
                ->get($madreUrl . '/api/users/search', ['q' => $query]);

            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['message' => 'Error buscando en App Madre'], $response->status());
            }
        } catch (\Exception $e) {
             return response()->json(['message' => 'Error de conexión: ' . $e->getMessage()], 500);
        }
    });

    Route::apiResource('garantias', App\Http\Controllers\GarantiaController::class);
    Route::apiResource('tipo-documentos', App\Http\Controllers\TipoDocumentoController::class);

    // Agencias y Sincronización
    Route::get('/agencias/sync-preview', [App\Http\Controllers\AgenciaController::class, 'previewSync']);
    Route::post('/agencias/sync', [App\Http\Controllers\AgenciaController::class, 'sync']);
    Route::post('/agencias/sync', [App\Http\Controllers\AgenciaController::class, 'sync']);
    Route::apiResource('agencias', App\Http\Controllers\AgenciaController::class);

    Route::apiResource('bufetes', App\Http\Controllers\BufeteController::class);
    Route::apiResource('registros-propiedad', App\Http\Controllers\RegistroPropiedadController::class);
    Route::apiResource('tipo-estados', App\Http\Controllers\TipoEstadoController::class);
    // Import Routes
Route::post('/import/upload', [App\Http\Controllers\ImportController::class, 'upload']);
Route::get('/import/status/{id}', [App\Http\Controllers\ImportController::class, 'status']);
Route::post('/import-nuevos/upload', [App\Http\Controllers\ImportController::class, 'uploadNuevos']);
Route::get('/import-nuevos/status/{id}', [App\Http\Controllers\ImportController::class, 'statusNuevos']);
Route::get('/expedientes', [App\Http\Controllers\ExpedienteHistoricoController::class, 'index']);
Route::post('/expedientes/search', [App\Http\Controllers\ExpedienteHistoricoController::class, 'search']);
Route::post('/expedientes/search-by-codigo', [App\Http\Controllers\ExpedienteHistoricoController::class, 'searchByCodigo']);

// Nuevos Expedientes (Mis Expedientes)
Route::get('/nuevos-expedientes', [App\Http\Controllers\NuevoExpedienteController::class, 'index']);
Route::post('/nuevos-expedientes/{id}/garantias', [App\Http\Controllers\NuevoExpedienteController::class, 'addGarantia']);
Route::get('/nuevos-expedientes/{id}/garantias', [App\Http\Controllers\NuevoExpedienteController::class, 'getGarantias']);
Route::post('/nuevos-expedientes/{id}/documentos', [App\Http\Controllers\NuevoExpedienteController::class, 'addDocumento']);
Route::delete('/nuevos-expedientes/{id}/documentos/{documentoId}', [App\Http\Controllers\NuevoExpedienteController::class, 'detachDocumento']);
Route::get('/nuevos-expedientes/{id}/detalles', [App\Http\Controllers\NuevoExpedienteController::class, 'getDetalles']);
Route::post('/documentos/check', [App\Http\Controllers\NuevoExpedienteController::class, 'checkDocumento']);
Route::put('/documentos/{id}', [App\Http\Controllers\NuevoExpedienteController::class, 'updateDocumento']);
Route::put('/nuevos-expedientes/{id}/garantias/{garantiaId}', [App\Http\Controllers\NuevoExpedienteController::class, 'updateGarantiaPivot']);
Route::post('/nuevos-expedientes/{id}/garantias/{garantiaId}/cambiar-tipo', [App\Http\Controllers\NuevoExpedienteController::class, 'changeGarantiaType']);

    // Seguimiento
    Route::post('/seguimiento/enviar-secretaria', [App\Http\Controllers\SeguimientoController::class, 'enviarASecretaria']);
    Route::get('/seguimiento/buzon-secretaria', [App\Http\Controllers\SeguimientoController::class, 'buzonSecretaria']);
    Route::post('/seguimiento/rechazar', [App\Http\Controllers\SeguimientoController::class, 'rechazarExpediente']);
    Route::post('/seguimiento/aceptar', [App\Http\Controllers\SeguimientoController::class, 'aceptarExpediente']);
    Route::post('/seguimiento/enviar-archivo', [App\Http\Controllers\SeguimientoController::class, 'enviarArchivo']);
    Route::post('/seguimiento/enviar-protocolo', [App\Http\Controllers\SeguimientoController::class, 'enviarProtocolo']);



    // Secretaria Credito
    Route::get('/secretaria-credito/solicitudes', [App\Http\Controllers\SecretariaCreditoController::class, 'index']);
    Route::post('/secretaria-credito/aceptar', [App\Http\Controllers\SecretariaCreditoController::class, 'aceptar']);
    Route::get('/secretaria-credito/aceptados', [App\Http\Controllers\SecretariaCreditoController::class, 'buzonAceptados']);
    Route::post('/secretaria-credito/enviar-abogado', [App\Http\Controllers\SecretariaCreditoController::class, 'enviarAbogado']);
    Route::get('/secretaria-credito/abogados', [App\Http\Controllers\SecretariaCreditoController::class, 'buzonAbogados']);
    Route::get('/abogado/buzon', [App\Http\Controllers\AbogadoController::class, 'buzon']);

    // Secretaria Agencia
    Route::post('/secretaria-agencia/adjuntar-contrato', [App\Http\Controllers\SecretariaAgenciaController::class, 'adjuntarContrato']);
    Route::post('/secretaria-agencia/archivar-administrativo', [App\Http\Controllers\SecretariaAgenciaController::class, 'archivarAdministrativamente']);
    Route::get('/secretaria-agencia/archivados', [App\Http\Controllers\SecretariaAgenciaController::class, 'buzonArchivados']);
});

