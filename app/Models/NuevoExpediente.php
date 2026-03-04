<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NuevoExpediente extends Model
{
    use HasFactory;

    protected $table = 'nuevos_expedientes';
    // protected $primaryKey = 'id'; // Default
    // public $incrementing = true; // Default

    protected $fillable = [
        'codigo_cliente',
        'id_agencia',
        'numero_documento',
        'usuario_asesor',
        'tasa_interes',
        'monto_documento',
        'tipo_garantia',
        'fecha_inicio',
        'cui',
        'nombre_asociado',
        'estado',
        'id_lote',
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
        return $this->hasMany(DetalleGarantia::class, 'nuevo_expediente_id');
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
                    ->withPivot('estado')
                    ->withTimestamps();
    }

    /**
     * Get the tracking history for the expediente.
     */
    public function seguimientos()
    {
        return $this->hasMany(SeguimientoExpediente::class, 'id_expediente');
    }

    /**
     * Get the tracking dates for the expediente.
     */
    public function fechas()
    {
        // 1:1 relationship
        return $this->hasOne(SeguimientoFecha::class, 'id_expediente');
    }

    /**
     * Get the agency associated with the expediente.
     */
    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia', 'id');
    }

    /**
     * Get the advisor (user) associated with the expediente.
     */
    public function asesor()
    {
        // 'usuario_asesor' in nuevos_expedientes matches 'username' in users
        return $this->belongsTo(User::class, 'usuario_asesor', 'username');
    }

    /**
     * Get the batch (lote) associated with the expediente.
     */
    public function lote()
    {
        return $this->belongsTo(LoteImportacion::class, 'id_lote');
    }
}
