<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    use HasFactory;

    protected $table = 'expedientes';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'codigo_cliente';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'codigo_cliente',
        'agencia',
        'fecha_inicio',
        'cta_bw',
        'numero_documento',
        'cif',
        'asociado',
        'monto',
        'tipo_garantia',
        'datos_garantia',
        'contrato',
        'inscripcion_otros_contratos',
        'ingreso',
        'inventario',
        'salida',
        'observacion',
        'estado',
        'localizacion'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'tasa_interes' => 'decimal:2',
        'monto' => 'decimal:2',
    ];
}
