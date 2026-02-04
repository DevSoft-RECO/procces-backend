<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Jobs\ImportExpedientesJob;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    /**
     * Handle the file upload and dispatch the import job.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'desde' => 'nullable|string|size:8', // YYYYMMDD
            'hasta' => 'nullable|string|size:8',
            'full' => 'nullable|boolean'
        ]);

        try {
            $file = $request->file('file');
            $fileName = 'import_' . time() . '.csv';
            // Save to storage/app/import using 'local' disk explicitly
            $path = $file->storeAs('import', $fileName, 'local');
            $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

            $jobId = (string) Str::uuid();
            $dates = [
                'desde' => $request->desde,
                'hasta' => $request->hasta,
                'full' => filter_var($request->full, FILTER_VALIDATE_BOOLEAN)
            ];

            // Dispatch Job
            ImportExpedientesJob::dispatch($absolutePath, $dates, $jobId);

            // Initialize cache
            Cache::put("import_job_{$jobId}", [
                'status' => 'uploading',
                'progress' => 0,
                'message' => 'Archivo subido. Encolando proceso...'
            ], 3600);

            return response()->json([
                'success' => true,
                'jobId' => $jobId,
                'message' => 'Proceso iniciado correctamente.'
            ]);

        } catch (\Exception $e) {
            Log::error("Upload Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle the file upload for Nuevos Expedientes.
     */
    public function uploadNuevos(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $file = $request->file('file');
            $fileName = 'import_nuevos_' . time() . '.csv';
            $path = $file->storeAs('import', $fileName, 'local');
            $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

            $jobId = (string) Str::uuid();

            // Dispatch Job
            \App\Jobs\ImportNuevosExpedientesJob::dispatch($absolutePath, $jobId);

            // Initialize cache
            $cacheKey = "import_nuevos_job_{$jobId}";
            Cache::put($cacheKey, [
                'status' => 'uploading',
                'progress' => 0,
                'message' => 'Archivo subido. Encolando proceso nuevos...'
            ], 3600);

            return response()->json([
                'success' => true,
                'jobId' => $jobId,
                'message' => 'Proceso de nuevos expedientes iniciado.'
            ]);

        } catch (\Exception $e) {
            Log::error("Upload Nuevos Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function statusNuevos($id)
    {
        $status = Cache::get("import_nuevos_job_{$id}");

        if (!$status) {
            return response()->json([
                'status' => 'not_found',
                'progress' => 0,
                'message' => 'No se encontró el proceso o ha expirado.'
            ]);
        }

        return response()->json($status);
    }

    /**
     * Get the status of an active job.
     */
    public function status($id)
    {
        $status = Cache::get("import_job_{$id}");

        if (!$status) {
            return response()->json([
                'status' => 'not_found',
                'progress' => 0,
                'message' => 'No se encontró el proceso o ha expirado.'
            ]);
        }

        return response()->json($status);
    }
}
