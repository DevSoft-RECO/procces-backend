<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoFecha extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'seguimiento_fechas';
    protected $primaryKey = 'id_expediente';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id_expediente',
        'f_enviado_secretaria',
        'f_retorno_asesores',
        'f_aceptado_secretaria',
        'f_enviado_archivos',
        'f_enviado_protocolos',
        'f_almacenado_admin',

        'f_aceptado_secretaria_credito',
        'f_enviado_abogado',
        'f_aceptado_abogado',
        'f_enviado_secretaria_credito',

        'f_ultimo_rechazo',
    ];

    protected $casts = [
        'f_enviado_secretaria' => 'datetime',
        'f_retorno_asesores' => 'datetime',
        'f_aceptado_secretaria' => 'datetime',
        'f_enviado_archivos' => 'datetime',
        'f_enviado_protocolos' => 'datetime',
        'f_almacenado_admin' => 'datetime',

        'f_aceptado_secretaria_credito' => 'datetime',
        'f_enviado_abogado' => 'datetime',
        'f_aceptado_abogado' => 'datetime',
        'f_enviado_secretaria_credito' => 'datetime',

        'f_ultimo_rechazo' => 'datetime',
    ];

    public function expediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'id_expediente');
    }
}
