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
        'recibi_pagare',
        'recibi_garantia_real',
        'recibi_contrato',
        'observacion_legal',
        'archivado_at',
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
}
