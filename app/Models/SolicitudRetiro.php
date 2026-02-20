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
        'id_expediente_historico',
        'numero_documento',
        'fecha_documento',
        'id_documento',
        'titulo_nombre',
        'id_agencia',
        'id_usuario_solicitante',
        'tipo_retiro',
        'justificacion',
        'fecha_solicitud',
        'id_usuario_despacho',
        'fecha_envio',
        'id_usuario_entrega',
        'id_agencia_entrega',
        'evidencia_entrega_path',
        'estado_actual',
    ];

    protected $casts = [
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

    // Relaciones de Entrega
    public function entregador()
    {
        return $this->belongsTo(User::class, 'id_usuario_entrega');
    }

    public function agenciaEntrega()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia_entrega');
    }

    public function documento()
    {
        // Relación por número de documento (no ID estándar)
        return $this->belongsTo(Documento::class, 'numero_documento', 'numero');
    }

    public function documentoRegistrado()
    {
        // Relación exacta por ID para documentos registrados en este flujo
        return $this->belongsTo(Documento::class, 'id_documento');
    }

    public function expedienteHistorico()
    {
        // Relación por ID autoincremental de la tabla expedientes
        return $this->belongsTo(Expediente::class, 'id_expediente_historico', 'id');
    }
}
