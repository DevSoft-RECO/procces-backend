<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfirmacionDocumento extends Model
{
    protected $table = 'confirmaciones_documentos';

    protected $fillable = [
        'documento_id',
        'user_id',
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
        'fecha_confirmacion',
        'confirmacion',
        'observacion_confirmacion',
        'archivado',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
