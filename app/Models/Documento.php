<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    use HasFactory;

    protected $table = 'documentos';

    protected $fillable = [
        'numero',
        'fecha',
        'propietario',
        'autorizador',
        'no_finca',
        'folio',
        'libro',
        'no_dominio',
        'referencia',
        'monto_poliza',
        'observacion',
        'tipo_documento_id',
        'registro_propiedad_id',
    ];

    /**
     * Get the TipoDocumento associated with the document.
     */
    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /**
     * Get the RegistroPropiedad associated with the document.
     */
    public function registroPropiedad()
    {
        return $this->belongsTo(RegistroPropiedad::class, 'registro_propiedad_id');
    }

    /**
     * Get the expedientes associated with this document.
     */
    public function nuevosExpedientes()
    {
        return $this->belongsToMany(NuevoExpediente::class, 'documento_nuevo_expediente', 'documento_id', 'nuevo_expediente_id')
                    ->withTimestamps();
    }
}
