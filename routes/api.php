<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http; // Added this use statement
use App\Http\Controllers\BuscarEditarController;
use App\Http\Controllers\SolicitudRetiroController; // Added missing import

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

  // Dashboard Analytics
Route::prefix('dashboard')->group(function () {
    Route::get('/kpi', [App\Http\Controllers\DashboardController::class, 'kpi']);
    Route::get('/pipeline', [App\Http\Controllers\DashboardController::class, 'pipeline']);
    Route::get('/advisors', [App\Http\Controllers\DashboardController::class, 'advisors']);
    Route::get('/rejections', [App\Http\Controllers\DashboardController::class, 'rejections']);
    Route::get('/agencies', [App\Http\Controllers\DashboardController::class, 'agencies']);
    Route::get('/agencies-list', [App\Http\Controllers\DashboardController::class, 'agenciesList']);
    Route::get('/trends', [App\Http\Controllers\DashboardController::class, 'trends']);
    Route::get('/processing-times', [App\Http\Controllers\DashboardController::class, 'processingTimes']);
});

// Catalog Routes
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
Route::put('/expedientes/{id}', [App\Http\Controllers\ExpedienteHistoricoController::class, 'update']);

//buscarexpedeintes


Route::get('expedientes/search-edit', [BuscarEditarController::class, 'searchEdit']);
Route::get('expedientes/documentos/{id}/asociados', [BuscarEditarController::class, 'getExpedientesAsociados']);

// Nuevos Expedientes (Mis Expedientes)
Route::get('/nuevos-expedientes', [App\Http\Controllers\NuevoExpedienteController::class, 'index']);
Route::get('/nuevos-expedientes/finalizados', [App\Http\Controllers\NuevoExpedienteController::class, 'buzonFinalizados']); // New Route
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
    Route::post('/seguimiento/observacion-legal', [App\Http\Controllers\SeguimientoController::class, 'actualizarObservacionLegal']);



    // Secretaria Credito
    Route::get('/secretaria-credito/solicitudes', [App\Http\Controllers\SecretariaCreditoController::class, 'index']);
    Route::post('/secretaria-credito/aceptar', [App\Http\Controllers\SecretariaCreditoController::class, 'aceptar']);
    Route::get('/secretaria-credito/aceptados', [App\Http\Controllers\SecretariaCreditoController::class, 'buzonAceptados']);
    Route::post('/secretaria-credito/enviar-abogado', [App\Http\Controllers\SecretariaCreditoController::class, 'enviarAbogado']);
    Route::get('/secretaria-credito/abogados', [App\Http\Controllers\SecretariaCreditoController::class, 'buzonAbogados']);
    Route::get('/abogado/buzon', [App\Http\Controllers\AbogadoController::class, 'buzon']);
    Route::post('/abogado/recibir', [App\Http\Controllers\AbogadoController::class, 'recibir']);
    Route::post('/abogado/enviar-secretaria', [App\Http\Controllers\AbogadoController::class, 'enviarSecretaria']);
    Route::get('/abogado/devueltos', [App\Http\Controllers\AbogadoController::class, 'devueltos']);

    Route::get('/secretaria-credito/escanear-documentos', [App\Http\Controllers\SecretariaCreditoController::class, 'escanearDocumentos']);
    Route::post('/secretaria-credito/guardar-escaneado', [App\Http\Controllers\SecretariaCreditoController::class, 'guardarEscaneado']);
    Route::get('/secretaria-credito/ver-contrato/{id}', [App\Http\Controllers\SecretariaCreditoController::class, 'verContrato']);
    Route::post('/secretaria-credito/finalizar-proceso', [App\Http\Controllers\SecretariaCreditoController::class, 'finalizarProceso']);

    // Secretaria Agencia
    Route::post('/secretaria-agencia/adjuntar-contrato', [App\Http\Controllers\SecretariaAgenciaController::class, 'adjuntarContrato']);
    Route::post('/secretaria-agencia/archivar-administrativamente', [App\Http\Controllers\SecretariaAgenciaController::class, 'archivarAdministrativamente']);
    Route::get('/secretaria-agencia/buzon-archivados', [App\Http\Controllers\SecretariaAgenciaController::class, 'buzonArchivados']);
    // Route::get('/secretaria-agencia/buzon-pagares', [App\Http\Controllers\SecretariaAgenciaController::class, 'buzonPagares']);
    Route::post('/secretaria-agencia/recibir-pagare', [App\Http\Controllers\SecretariaAgenciaController::class, 'recibirPagare']);
    // Route::post('/secretaria-agencia/archivar-pagare', [App\Http\Controllers\SecretariaAgenciaController::class, 'archivarPagare']);

    // Archivo Module
    Route::get('/archivo/buzon-recibidos', [App\Http\Controllers\ArchivoController::class, 'buzonRecibidos']);
    Route::post('/archivo/recibir-garantia/{id}', [App\Http\Controllers\ArchivoController::class, 'recibirGarantiaReal']);
    Route::post('/archivo/recibir-contrato/{id}', [App\Http\Controllers\ArchivoController::class, 'recibirContrato']);
    Route::post('/archivo/archivar/{id}', [App\Http\Controllers\ArchivoController::class, 'archivar']);
    Route::get('/archivo/sistema', [App\Http\Controllers\ArchivoController::class, 'archivoSistema']);
    Route::get('/archivo/detalle/{id}', [App\Http\Controllers\ArchivoController::class, 'show']);

    // Tracking (Historial Centralizado)
    Route::get('/tracking/{codigo_cliente}', [App\Http\Controllers\TrackingController::class, 'getHistory']);

    // Traslado de Expedientes
    Route::get('/traslado-expedientes/search', [App\Http\Controllers\TrasladarExpedientesController::class, 'search']);
    Route::put('/traslado-expedientes/{id}/asesor', [App\Http\Controllers\TrasladarExpedientesController::class, 'updateAsesor']);

    // Edición de Seguimiento
    Route::get('/editar-seguimiento/search', [App\Http\Controllers\EditarSeguimientoController::class, 'search']);
    Route::put('/editar-seguimiento/{id}', [App\Http\Controllers\EditarSeguimientoController::class, 'update']);

    // Retiro de Garantías
    Route::post('/solicitudes-retiro/search', [SolicitudRetiroController::class, 'search']);
    Route::post('/solicitudes-retiro', [SolicitudRetiroController::class, 'store']); // Solicitudes Retiro
    Route::get('/solicitudes-retiro/agencia', [SolicitudRetiroController::class, 'indexAgency']);
    Route::get('/solicitudes-retiro/incoming', [SolicitudRetiroController::class, 'indexIncoming']);
    Route::get('/solicitudes-retiro/pending-delivery', [SolicitudRetiroController::class, 'indexPendingDelivery']);
    Route::get('/solicitudes-retiro/delivered', [SolicitudRetiroController::class, 'indexDelivered']); // Delivered Mailbox
    Route::get('/solicitudes-retiro/archivo', [SolicitudRetiroController::class, 'indexArchive']); // Restored Route
    Route::post('/solicitudes-retiro/{id}/despachar', [SolicitudRetiroController::class, 'dispatchRequest']); // Restored Route
    Route::post('/solicitudes-retiro/{id}/confirm-receipt', [SolicitudRetiroController::class, 'confirmReceipt']);
    Route::post('/solicitudes-retiro/{id}/deliver', [SolicitudRetiroController::class, 'deliverToAssociate']);
    Route::post('/solicitudes-retiro/{id}/return-archive', [SolicitudRetiroController::class, 'returnToArchive']); // New Route
    Route::apiResource('solicitudes-retiro', SolicitudRetiroController::class);

    // Confirmación de Documentos
    Route::post('/confirmacion-documentos/search', [App\Http\Controllers\ConfirmacionDocController::class, 'search']);
    Route::post('/confirmacion-documentos', [App\Http\Controllers\ConfirmacionDocController::class, 'store']);
    Route::get('/confirmacion-documentos', [App\Http\Controllers\ConfirmacionDocController::class, 'index']); // Admin Index
    Route::put('/confirmacion-documentos/{id}', [App\Http\Controllers\ConfirmacionDocController::class, 'update']); // Admin Update
    Route::get('/confirmacion-documentos/resultados', [App\Http\Controllers\ConfirmacionDocController::class, 'indexResults']);
    Route::get('/confirmacion-documentos/historico', [App\Http\Controllers\ConfirmacionDocController::class, 'indexHistory']);
    Route::put('/confirmacion-documentos/{id}/archive', [App\Http\Controllers\ConfirmacionDocController::class, 'archive']);
});
