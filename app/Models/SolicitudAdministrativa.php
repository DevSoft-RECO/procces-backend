<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudAdministrativa extends Model
{
    protected $table = 'solicitudes_administrativas';

    protected $fillable = [
        'id_expediente',
        'id_usuario_solicita',
        'id_agencia',
        'fecha_solicitud',
        'estado_solicitud',
        'id_usuario_despacho',
        'fecha_despacho',
        'confirmacion_solicitante',
        'fecha_devolucion_iniciada',
        'confirmacion_reingreso',
        'fecha_finalizacion',
        'observaciones',
        'observacion_despacho',
        'estado',
    ];

    // --- Relaciones ---

    public function expediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'id_expediente');
    }

    public function usuarioSolicita()
    {
        return $this->belongsTo(User::class, 'id_usuario_solicita');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia');
    }

    public function usuarioDespacho()
    {
        return $this->belongsTo(User::class, 'id_usuario_despacho');
    }
}
