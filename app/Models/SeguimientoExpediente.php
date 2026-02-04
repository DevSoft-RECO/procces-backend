<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoExpediente extends Model
{
    use HasFactory;

    protected $table = 'seguimiento_expedientes';

    protected $fillable = [
        'nuevo_expediente_id',
        'usuario',
        'paso',
        'estado',
        'observacion',
    ];

    /**
     * Get the expediente associated with this tracking record.
     */
    public function nuevoExpediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'nuevo_expediente_id', 'codigo_cliente');
    }
}
