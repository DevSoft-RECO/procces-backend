<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

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
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'actualizacion' => 'date',
        'fecha_apertura' => 'date',
        'fecha_nacimiento' => 'date',
        'fecha_ingreso_laboral' => 'date',
        'fecha_inicio_negocio' => 'date',
        'fecha_expdpi' => 'date',
        'fecha_emidpi' => 'date',
        'ingresos_laborales' => 'decimal:5',
        'ingresos_negocio_propio' => 'decimal:5',
        'ingresos_remesas' => 'decimal:5',
        'monto_otros_ingresos' => 'decimal:5',
        'otros_ingresos' => 'decimal:5',
        'monto_ingresos' => 'decimal:5',
        'monto_egresos' => 'decimal:5',
    ];
}
