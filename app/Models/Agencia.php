<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    use HasFactory;

    // El ID es manual (espejo de la Madre)
    public $incrementing = false;
    protected $keyType = 'int'; // ID es unsignedBigInteger

    protected $fillable = [
        'id',
        'nombre',
        'codigo',
    ];
}
