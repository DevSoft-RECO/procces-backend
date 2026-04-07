<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ReporteExportacion;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerarReporteHistoricoArchivoJob implements ShouldQueue
{
    use Queueable;

    protected $reporteId;

    /**
     * Create a new job instance.
     */
    public function __construct($reporteId)
    {
        $this->reporteId = $reporteId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reporte = ReporteExportacion::find($this->reporteId);

        if (!$reporte) {
            return;
        }

        try {
            $reporte->update(['estado' => 'procesando', 'progreso_porcentaje' => 5]);

            $fileName = 'historico_archivo_' . $reporte->id . '_' . date('Y-m-d_H-i-s') . '.csv';

            // Usaremos un archivo temporal local primero
            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            // Escribir BOM para que Excel lea los acentos UTF-8 correctamente
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados del CSV (Datos Puros de Expediente)
            $columns = [
                'ID', 'Código Cliente', 'Agencia', 'Fecha Inicio', 'CTA BW', 
                'Número Documento', 'CIF', 'Asociado', 'Monto', 'Tipo Garantía', 
                'Datos Garantía', 'Contrato', 'Inscripción Otros Contrados', 
                'Ingreso', 'Inventario', 'Salida', 'Observación', 'Estado', 
                'Localización', 'Fecha Registro'
            ];

            fputcsv($file, $columns, ',', '"', '"');

            $query = DB::table('expedientes')
                ->select(
                    'id', 'codigo_cliente', 'agencia', 'fecha_inicio', 'cta_bw',
                    'numero_documento', 'cif', 'asociado', 'monto', 'tipo_garantia',
                    'datos_garantia', 'contrato', 'inscripcion_otros_contratos',
                    'ingreso', 'inventario', 'salida', 'observacion', 'estado',
                    'localizacion', 'created_at'
                )
                ->orderBy('id', 'DESC');

            $totalRecords = $query->count();
            $processedRecords = 0;
            $chunkSize = 1000;

            if ($totalRecords === 0) {
                $reporte->update(['progreso_porcentaje' => 100]);
            } else {
                $query->chunk($chunkSize, function ($expedientes) use ($file, &$processedRecords, $totalRecords, $reporte) {
                    foreach ($expedientes as $row) {
                        fputcsv($file, [
                            $this->sanitizeValue($row->id), 
                            $this->sanitizeValue($row->codigo_cliente), 
                            $this->sanitizeValue($row->agencia), 
                            $this->sanitizeValue($row->fecha_inicio), 
                            $this->sanitizeValue($row->cta_bw),
                            $this->sanitizeValue($row->numero_documento), 
                            $this->sanitizeValue($row->cif), 
                            $this->sanitizeValue($row->asociado), 
                            $this->sanitizeValue($row->monto), 
                            $this->sanitizeValue($row->tipo_garantia),
                            $this->sanitizeValue($row->datos_garantia), 
                            $this->sanitizeValue($row->contrato), 
                            $this->sanitizeValue($row->inscripcion_otros_contratos),
                            $this->sanitizeValue($row->ingreso), 
                            $this->sanitizeValue($row->inventario), 
                            $this->sanitizeValue($row->salida), 
                            $this->sanitizeValue($row->observacion), 
                            $this->sanitizeValue($row->estado),
                            $this->sanitizeValue($row->localizacion), 
                            $this->sanitizeValue($row->created_at)
                        ], ',', '"', '"');
                    }
                    $processedRecords += count($expedientes);
                    $porcentaje = min(95, ceil(($processedRecords / $totalRecords) * 100));
                    $reporte->update(['progreso_porcentaje' => $porcentaje]);
                });
            }

            fclose($file);

            // Mover el archivo temporal al Storage oficial configurado en Laravel
            $finalPath = 'reportes/' . $fileName;
            Storage::disk('local')->put($finalPath, file_get_contents($tempPath));

            // Eliminar el temp
            unlink($tempPath);

            $reporte->update([
                'estado' => 'completado',
                'progreso_porcentaje' => 100,
                'file_path' => $finalPath
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando reporte Historico de Archivo: ' . $e->getMessage());
            $reporte->update([
                'estado' => 'fallido',
                'error_msg' => 'Fallo la generación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Limpia y normaliza un valor para evitar que rompa el formato del CSV.
     * Especialmente útil para datos antiguos con codificación mixta o símbolos extraños.
     */
    private function sanitizeValue($value)
    {
        if ($value === null) {
            return '';
        }

        // Asegurar que sea string
        $value = (string)$value;

        // Limpiar saltos de línea y tabulaciones que rompen la estructura de filas
        $value = str_replace(["\r\n", "\r", "\n", "\t"], " ", $value);

        // Convertir codificación si es necesario para evitar caracteres rotos (como NÃšMERO)
        // Intentamos detectar si es UTF-8, si no, intentamos convertir desde ISO-8859-1
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        // Eliminar caracteres nulos o de control extraños
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return trim($value);
    }
}
