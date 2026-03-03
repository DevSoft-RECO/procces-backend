<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoteImportacion extends Model
{
    use HasFactory;

    protected $table = 'lotes_importacion';

    protected $fillable = [
        'nombre_archivo',
        'usuario_id',
        'registros_totales',
    ];

    public function nuevosExpedientes()
    {
        return $this->hasMany(NuevoExpediente::class, 'id_lote');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
