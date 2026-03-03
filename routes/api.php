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
Route::get('/import-nuevos/lotes', [App\Http\Controllers\BatchManagementController::class, 'index']);
Route::delete('/import-nuevos/lotes/{id}', [App\Http\Controllers\BatchManagementController::class, 'destroy']);

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
    Route::get('/abogado/exportar-devueltos-csv', [App\Http\Controllers\AbogadoController::class, 'exportarDevueltosCSV']);

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

    // Exportaciones (Analítica Cruda CSV Asíncrona)
    Route::post('/exportar/seguimiento-csv', [\App\Http\Controllers\Seguimiento\ExportacionSegaController::class, 'dispatchReport']);
    Route::post('/exportar/general-agencias', [\App\Http\Controllers\Seguimiento\ReporteGeneralAgenciaController::class, 'dispatchReport']);
    Route::post('/exportar/general-asesor', [\App\Http\Controllers\Seguimiento\ReporteGeneralAsesorController::class, 'dispatchReport']);
    Route::post('/exportar/general-documentos', [\App\Http\Controllers\Seguimiento\ReporteDocumentosController::class, 'dispatchReport']);
    Route::post('/exportar/general-solicitudes-admin', [\App\Http\Controllers\Seguimiento\ReporteSolicitudesAdminController::class, 'dispatchReport']);
    Route::post('/exportar/general-solicitudes-retiros', [\App\Http\Controllers\Seguimiento\ReporteSolicitudesRetiroController::class, 'dispatchReport']);
    Route::post('/exportar/general-confirmaciones', [\App\Http\Controllers\Seguimiento\ReporteConfirmacionesController::class, 'dispatchReport']);
    Route::get('/exportar/mis-reportes', [\App\Http\Controllers\Seguimiento\ExportacionSegaController::class, 'listReports']);
    Route::get('/exportar/descargar/{id}', [\App\Http\Controllers\Seguimiento\ExportacionSegaController::class, 'downloadReport']);
    Route::delete('/exportar/borrar-todos', [\App\Http\Controllers\Seguimiento\ExportacionSegaController::class, 'destroyAll']);
    Route::delete('/exportar/borrar/{id}', [\App\Http\Controllers\Seguimiento\ExportacionSegaController::class, 'destroy']);

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
    Route::get('/solicitudes-retiro/agencia/historico', [SolicitudRetiroController::class, 'indexAgencyHistory']);
    Route::get('/solicitudes-retiro/incoming', [SolicitudRetiroController::class, 'indexIncoming']);
    Route::get('/solicitudes-retiro/pending-delivery', [SolicitudRetiroController::class, 'indexPendingDelivery']);
    Route::get('/solicitudes-retiro/delivered', [SolicitudRetiroController::class, 'indexDelivered']); // Delivered Mailbox
    Route::get('/solicitudes-retiro/archivo', [SolicitudRetiroController::class, 'indexArchive']); // Restored Route
    Route::post('/solicitudes-retiro/{id}/despachar', [SolicitudRetiroController::class, 'dispatchRequest']); // Restored Route
    Route::delete('/solicitudes-retiro/{id}', [SolicitudRetiroController::class, 'destroy']); // Delete Route
    Route::post('/solicitudes-retiro/{id}/confirm-receipt', [SolicitudRetiroController::class, 'confirmReceipt']);
    Route::post('/solicitudes-retiro/{id}/deliver', [SolicitudRetiroController::class, 'deliverToAssociate']);
    Route::get('/solicitudes-retiro/{id}/ver-evidencia', [SolicitudRetiroController::class, 'verEvidencia']);
    Route::post('/solicitudes-retiro/{id}/return-archive', [SolicitudRetiroController::class, 'returnToArchive']); // New Route
    Route::post('/solicitudes-retiro/{id}/confirm-return', [SolicitudRetiroController::class, 'confirmReturn']);
    Route::post('/solicitudes-retiro/{id}/register-document', [SolicitudRetiroController::class, 'registerDocument']); // New Route for Historical
    Route::apiResource('solicitudes-retiro', SolicitudRetiroController::class);

    // Retiro Administrativo
    Route::get('/solicitudes-administrativas/buscar', [\App\Http\Controllers\SolicitudesAdministrativas\SolicitanteController::class, 'buscarExpediente']);
    Route::post('/solicitudes-administrativas', [\App\Http\Controllers\SolicitudesAdministrativas\SolicitanteController::class, 'crearSolicitud']);
    Route::get('/solicitudes-administrativas', [\App\Http\Controllers\SolicitudesAdministrativas\SolicitanteController::class, 'index']);
    Route::get('/solicitudes-administrativas/historico', [\App\Http\Controllers\SolicitudesAdministrativas\SolicitanteController::class, 'historico']);
    Route::post('/solicitudes-administrativas/{id}/confirmar', [\App\Http\Controllers\SolicitudesAdministrativas\SolicitanteController::class, 'confirmarRecepcion']);
    Route::post('/solicitudes-administrativas/{id}/devolver', [\App\Http\Controllers\SolicitudesAdministrativas\SolicitanteController::class, 'iniciarDevolucion']);

    // Despacho Administrativo
    Route::get('/solicitudes-administrativas/admin', [\App\Http\Controllers\SolicitudesAdministrativas\DespachoController::class, 'index']);
    Route::post('/solicitudes-administrativas/{id}/aceptar', [\App\Http\Controllers\SolicitudesAdministrativas\DespachoController::class, 'aceptarSolicitud']);
    Route::post('/solicitudes-administrativas/{id}/despachar', [\App\Http\Controllers\SolicitudesAdministrativas\DespachoController::class, 'despacharExpediente']);
    Route::post('/solicitudes-administrativas/{id}/reingreso', [\App\Http\Controllers\SolicitudesAdministrativas\DespachoController::class, 'confirmarReingreso']);

    // Confirmación de Documentos
    Route::post('/confirmacion-documentos/search', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'search']);
    Route::post('/confirmacion-documentos', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'store']);
    Route::get('/confirmacion-documentos', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'index']); // Admin Index
    Route::put('/confirmacion-documentos/{id}', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'update']); // Admin Update
    Route::post('/confirmacion-documentos/{id}/register-document', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'registerDocument']); // New Route
    Route::get('/confirmacion-documentos/resultados', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'indexResults']);
    Route::get('/confirmacion-documentos/historico', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'indexHistory']);
    Route::put('/confirmacion-documentos/{id}/archive', [\App\Http\Controllers\SolicitudConfirmacion\ConfirmacionDocController::class, 'archive']);

    // Edición de Garantías (Documentos) y Detalles
    Route::get('/documentos-edicion/search', [App\Http\Controllers\DocumentoEdicionController::class, 'search']);
    Route::put('/documentos-edicion/{id}', [App\Http\Controllers\DocumentoEdicionController::class, 'update']);
    Route::get('/detalle-garantia-edicion/search', [App\Http\Controllers\DetalleGarantiaEdicionController::class, 'search']);
    Route::put('/detalle-garantia-edicion/{id}', [App\Http\Controllers\DetalleGarantiaEdicionController::class, 'update']);
    Route::get('/nuevo-expediente-edicion/search', [App\Http\Controllers\NuevoExpedienteEdicionController::class, 'search']);
    Route::get('/nuevo-expediente-edicion/catalogs', [App\Http\Controllers\NuevoExpedienteEdicionController::class, 'getCatalogs']);
    Route::put('/nuevo-expediente-edicion/{id}', [App\Http\Controllers\NuevoExpedienteEdicionController::class, 'update']);

    Route::get('/solicitud-administrativa-edicion/search', [App\Http\Controllers\SolicitudAdministrativaEdicionController::class, 'search']);
    Route::get('/solicitud-administrativa-edicion/catalogs', [App\Http\Controllers\SolicitudAdministrativaEdicionController::class, 'getCatalogs']);
    Route::put('/solicitud-administrativa-edicion/{id}', [App\Http\Controllers\SolicitudAdministrativaEdicionController::class, 'update']);

    // Edición de Solicitud de Retiro (Garantías)
    Route::get('/solicitud-retiro-edicion/search', [App\Http\Controllers\SolicitudRetiroEdicionController::class, 'search']);
    Route::get('/solicitud-retiro-edicion/catalogs', [App\Http\Controllers\SolicitudRetiroEdicionController::class, 'getCatalogs']);
    Route::put('/solicitud-retiro-edicion/{id}', [App\Http\Controllers\SolicitudRetiroEdicionController::class, 'update']);

    // Edición de Confirmación de Garantías
    Route::get('/confirmacion-garantias-edicion/search', [App\Http\Controllers\ConfirmacionDocumentoEdicionController::class, 'search']);
    Route::get('/confirmacion-garantias-edicion/catalogs', [App\Http\Controllers\ConfirmacionDocumentoEdicionController::class, 'getCatalogs']);
    Route::put('/confirmacion-garantias-edicion/{id}', [App\Http\Controllers\ConfirmacionDocumentoEdicionController::class, 'update']);
});
