<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleGarantia extends Model
{
    use HasFactory;

    protected $table = 'detalle_garantia';

    protected $fillable = [
        'nuevo_expediente_id',
        'garantia_id',
        'codeudor1',
        'codeudor2',
        'codeudor3',
        'codeudor4',
        'observacion1',
        'observacion2',
        'observacion3',
        'observacion4',
    ];

    /**
     * Get the NuevoExpediente associated with this detail.
     */
    public function nuevoExpediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'nuevo_expediente_id', 'codigo_cliente');
    }

    /**
     * Get the Garantia associated with this detail.
     */
    public function garantia()
    {
        return $this->belongsTo(Garantia::class, 'garantia_id');
    }
}
