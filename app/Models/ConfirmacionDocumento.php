<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfirmacionDocumento extends Model
{
    protected $table = 'confirmaciones_documentos';

    protected $fillable = [
        'documento_id',
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
        'tipo_documento', // String name
        'registro_propiedad', // String name
        'fecha_consulta',
        'confirmacion',
        'observacion_confirmacion',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }
}
