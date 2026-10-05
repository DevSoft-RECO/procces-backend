<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoExpediente extends Model
{
    use HasFactory;

    protected $table = 'seguimiento_expedientes';
    protected $primaryKey = 'id_seguimiento';

    protected $fillable = [
        'id_expediente',
        'id_estado',
        'id_estado_secundario',
        'enviado_a_archivos',
        'archivo_administrativo',
        'observacion_envio',
        'observacion_rechazo',
        'tipo_contrato',
        'numero_contrato',
        'path_contrato',
        'bufete_id',
        'recibi_garantia_real',
        'recibi_contrato',
        'observacion_legal',
        'archivado_at',
        'modificacion',
    ];

    /**
     * Get the expediente associated with this tracking record.
     */
    public function nuevoExpediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'id_expediente');
    }

    /**
     * Relación con TipoEstado.
     */
    public function estado()
    {
        return $this->belongsTo(TipoEstado::class, 'id_estado', 'id');
    }

    /**
     * Relación con Bufete (Abogado).
     */
    public function bufete()
    {
        return $this->belongsTo(Bufete::class, 'bufete_id', 'id');
    }

    /**
     * Relación con TipoEstado (Secundario).
     */
    public function estadoSecundario()
    {
        return $this->belongsTo(TipoEstado::class, 'id_estado_secundario', 'id');
    }
        public function asesor()
    {
        // 'usuario_asesor' in nuevos_expedientes matches 'username' in users
        return $this->belongsTo(User::class, 'usuario_asesor', 'username');
    }

    /**
     * Marca masivamente los expedientes asociados a un documento como modificados incrementando la columna.
     */
    public static function marcarModificacionPorDocumento($documentoId)
    {
        try {
            // Obtener los IDs de expedientes vinculados a este documento desde la tabla pivot
            $expedienteIds = \DB::table('documento_nuevo_expediente')
                ->where('documento_id', $documentoId)
                ->pluck('nuevo_expediente_id');

            if ($expedienteIds->isNotEmpty()) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('seguimiento_expedientes', 'modificacion')) {
                    static::whereIn('id_expediente', $expedienteIds)
                        ->increment('modificacion');
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo marcar modificación para documento {$documentoId}: " . $e->getMessage());
        }
    }

    /**
     * Incrementa la columna modificacion para un expediente específico.
     */
    public static function marcarModificacionPorExpediente($expedienteId)
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('seguimiento_expedientes', 'modificacion')) {
                static::where('id_expediente', $expedienteId)
                    ->increment('modificacion');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo marcar modificación para expediente {$expedienteId}: " . $e->getMessage());
        }
    }
}
