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
    protected $fillable = [
        'codigo_cliente',
        'dpi',
        'numero_documento',
        'nombre1',
        'apellido1',
        'nombre_corto',
        'empresa',
        'tipo_documento',
        'usuario_asesor',
        'tipo_garantia',
        'tasa_interes',
        'monto_documento',
        'fecha_inicio',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fecha_inicio' => 'date',
        'tasa_interes' => 'decimal:2',
        'monto_documento' => 'decimal:2',
    ];
}
