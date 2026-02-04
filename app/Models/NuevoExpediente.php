<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NuevoExpediente extends Model
{
    use HasFactory;

    protected $table = 'nuevos_expedientes';
    protected $primaryKey = 'codigo_cliente';
    public $incrementing = false;

    protected $fillable = [
        'codigo_cliente',
        'id_agencia',
        'numero_documento',
        'tipo_documento',
        'usuario_asesor',
        'tasa_interes',
        'monto_documento',
        'tipo_garantia',
        'fecha_inicio',
        'cui',
        'nombre_asociado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'tasa_interes' => 'decimal:2',
        'monto_documento' => 'decimal:2',
    ];
}
