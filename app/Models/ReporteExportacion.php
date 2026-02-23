<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteExportacion extends Model
{
    protected $fillable = [
        'usuario_id',
        'tipo_reporte',
        'estado',
        'progreso_porcentaje',
        'file_path',
        'error_msg'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
