<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bufete extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agencia_id',
        'descripcion'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'agencia_id', 'id');
    }
}
