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

    /**
     * Get the details (pivot entries) for the guarantees.
     */
    public function detalleGarantias()
    {
        return $this->hasMany(DetalleGarantia::class, 'nuevo_expediente_id', 'codigo_cliente');
    }

    /**
     * Get the warranties associated with the expediente.
     */
    public function garantias()
    {
        return $this->belongsToMany(Garantia::class, 'detalle_garantia', 'nuevo_expediente_id', 'garantia_id')
                    ->withPivot([
                        'codeudor1', 'codeudor2', 'codeudor3', 'codeudor4',
                        'observacion1', 'observacion2', 'observacion3', 'observacion4'
                    ])
                    ->withTimestamps();
    }

    /**
     * Get the documents associated with the expediente.
     */
    public function documentos()
    {
        return $this->belongsToMany(Documento::class, 'documento_nuevo_expediente', 'nuevo_expediente_id', 'documento_id')
                    ->withTimestamps();
    }

    /**
     * Get the tracking history for the expediente.
     */
    public function seguimientos()
    {
        return $this->hasMany(SeguimientoExpediente::class, 'nuevo_expediente_id', 'codigo_cliente');
    }
}
