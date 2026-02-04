<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garantia extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    /**
     * Get the details (pivot entries) stored for this warranty.
     */
    public function detalleGarantias()
    {
        return $this->hasMany(DetalleGarantia::class, 'garantia_id');
    }

    /**
     * Get the expedientes associated with this warranty.
     */
    public function nuevosExpedientes()
    {
        return $this->belongsToMany(NuevoExpediente::class, 'detalle_garantia', 'garantia_id', 'nuevo_expediente_id')
                    ->withPivot([
                        'codeudor1', 'codeudor2', 'codeudor3', 'codeudor4',
                        'observacion1', 'observacion2', 'observacion3', 'observacion4'
                    ])
                    ->withTimestamps();
    }
}
