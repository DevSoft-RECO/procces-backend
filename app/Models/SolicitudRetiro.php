<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudRetiro extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_expedientes';

    protected $fillable = [
        'id_expediente',
        'numero_documento',
        'titulo_nombre',
        'es_manual',
        'id_agencia',
        'id_usuario_solicitante',
        'tipo_retiro',
        'justificacion',
        'fecha_solicitud',
        'id_usuario_despacho',
        'fecha_envio',
        'estado_actual',
    ];

    protected $casts = [
        'es_manual' => 'boolean',
        'fecha_solicitud' => 'datetime',
        'fecha_envio' => 'datetime',
    ];

    public function expediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'id_expediente');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia');
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'id_usuario_solicitante');
    }

    public function despachador()
    {
        return $this->belongsTo(User::class, 'id_usuario_despacho');
    }

    public function documento()
    {
        // Relación por número de documento (no ID estándar)
        return $this->belongsTo(Documento::class, 'numero_documento', 'numero');
    }

    public function expedienteHistorico()
    {
        // Relación por número de documento con Expediente (Histórico)
        // Usamos numero_documento como clave foránea y local
        return $this->belongsTo(Expediente::class, 'numero_documento', 'numero_documento');
    }
}
