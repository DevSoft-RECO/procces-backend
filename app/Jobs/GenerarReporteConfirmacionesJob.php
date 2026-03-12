<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\ReporteExportacion;
use Carbon\Carbon;

class GenerarReporteConfirmacionesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes timeout
    protected $descargaId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($descargaId, $userId)
    {
        $this->descargaId = $descargaId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $descarga = ReporteExportacion::find($this->descargaId);
            if (!$descarga) {
                return;
            }

            // Update status to processing
            $descarga->update(['estado' => 'procesando', 'progreso_porcentaje' => 10]);

            $fileName = 'Reporte_General_Confirmaciones_' . date('Y-m-d_H-i-s') . '_' . $this->descargaId . '.csv';
            // Usaremos un archivo temporal local primero
            $tempPath = storage_path('app/temp_' . $fileName);
            $file = fopen($tempPath, 'w');

            // Set CSV headers ensuring UTF-8 BOM representation for Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Column Header
            $columns = [
                'ID',
                'ID Documento Vinculado',
                'Nombre Solicitante',
                'Agencia Solicitante',
                'Código Cliente',
                'Número Producto',
                'Número Documento',
                'Fecha',
                'Propietario',
                'Autorizador',
                'No. Finca',
                'Folio',
                'Libro',
                'No. Dominio',
                'Referencia',
                'Monto Póliza',
                'Observación Solicitud',
                'Tipo Documento',
                'Registro Propiedad',
                'Fecha de Confirmación Física',
                'Estado de Confirmación',
                'Observación Confirmación',
                'Archivado (Histórico)',
                'Fecha Registro Fila'
            ];
            fputcsv($file, $columns);

            $query = DB::table('confirmaciones_documentos')
                ->leftJoin('agencias as a', 'confirmaciones_documentos.id_agencia', '=', 'a.id')
                ->select(
                    'confirmaciones_documentos.*',
                    'a.nombre as nombre_agencia'
                )
                ->orderBy('confirmaciones_documentos.id', 'DESC');

            $totalRecords = $query->count();

            if ($totalRecords === 0) {
                 fputcsv($file, ['No data found matching the exact criteria.']);
                 fclose($file);

                 // Mover el archivo temporal al Storage oficial
                 $finalPath = 'reportes/' . $fileName;
                 Storage::disk('local')->put($finalPath, file_get_contents($tempPath));
                 unlink($tempPath);

                 $descarga->update([
                     'estado' => 'completado',
                     'file_path' => $finalPath,
                     'progreso_porcentaje' => 100
                 ]);
                 return;
            }

            $query->chunk(1000, function ($rows) use ($file) {
                foreach ($rows as $row) {

                    // Parse Confirmacion to human readable string (It comes as 'SI' or 'NO' text string from Controller)
                    $confirmacionStr = "Pendiente";
                    if ($row->confirmacion === 'SI') {
                        $confirmacionStr = "Sí (Físico Encontrado)";
                    } elseif ($row->confirmacion === 'NO') {
                        $confirmacionStr = "No (Ya fue Retirado / No Encontrado)";
                    }

                    $archivadoStr = $row->archivado ? "Sí" : "No";

                    fputcsv($file, [
                        $row->id,
                        $row->documento_id,
                        $row->nombre_solicitante,
                        $row->nombre_agencia,
                        $row->codigo_cliente,
                        $row->numero_producto,
                        $row->numero,
                        $row->fecha,
                        $row->propietario,
                        $row->autorizador,
                        $row->no_finca,
                        $row->folio,
                        $row->libro,
                        $row->no_dominio,
                        $row->referencia,
                        $row->monto_poliza,
                        $row->observacion,
                        $row->tipo_documento,
                        $row->registro_propiedad,
                        $row->fecha_confirmacion,
                        $confirmacionStr,
                        $row->observacion_confirmacion,
                        $archivadoStr,
                        $row->created_at
                    ]);
                }
            });

            fclose($file);

            // Mover el archivo temporal al Storage oficial configurado en Laravel
            $finalPath = 'reportes/' . $fileName;
            Storage::disk('local')->put($finalPath, file_get_contents($tempPath));

            // Eliminar el temp
            unlink($tempPath);

            // Update status to completed
            $descarga->update([
                'estado' => 'completado',
                'file_path' => $finalPath,
                'progreso_porcentaje' => 100
            ]);

        } catch (\Exception $e) {
            if (isset($descarga)) {
                $descarga->update([
                    'estado' => 'fallido',
                    'error_msg' => 'Error al generar el CSV',
                ]);
            }
            \Log::error('Error generating general confirmaciones report: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
        }
    }
}
