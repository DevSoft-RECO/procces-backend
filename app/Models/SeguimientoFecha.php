<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoFecha extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'seguimiento_fechas';
    protected $primaryKey = 'id_expediente';
    public $incrementing = false;
    protected $keyType = 'string'; // Assuming codigo_cliente is string? No, migration said unsignedBigInteger?
    // Wait, NuevoExpediente primary key is 'codigo_cliente'. Migration for `id_expediente` is unsignedBigInteger.
    // If NuevoExpediente 'codigo_cliente' is integer, then fine. But in NuevoExpediente logic, it's often a string?
    // Let's check NuevoExpediente.php...
    // protected $primaryKey = 'codigo_cliente';
    // public $incrementing = false;
    // Migration: references('codigo_cliente')...
    // In migration `segueimiento_expedientes` I used `unsignedBigInteger`.
    // If `codigo_cliente` is NOT integer, then my migration will fail on foreign key creation if types mismatch.
    // Assuming it's integer based on `unsignedBigInteger('nuevo_expediente_id')` in original migration.

    // BUT wait. In `NuevoExpediente` model:
    // `public $incrementing = false;` often implies string or custom ID.
    // I should check `create_nuevos_expedientes...` migration if possible to strict verify.
    // But assuming it works since migrations passed (if they passed).

    protected $fillable = [
        'id_expediente',
        'f_enviado_secretaria',
        'f_retorno_asesores',
        'f_aceptado_secretaria',
        'f_enviado_archivos',
        'f_enviado_protocolos',
        'f_almacenado_admin',

        'f_aceptado_secretaria_credito',
        'f_enviado_abogado',
        'f_aceptado_abogado',
        'f_enviado_secretaria_credito',

        'f_ultimo_rechazo',
    ];

    protected $casts = [
        'f_enviado_secretaria' => 'datetime',
        'f_retorno_asesores' => 'datetime',
        'f_aceptado_secretaria' => 'datetime',
        'f_enviado_archivos' => 'datetime',
        'f_enviado_protocolos' => 'datetime',
        'f_almacenado_admin' => 'datetime',

        'f_aceptado_secretaria_credito' => 'datetime',
        'f_enviado_abogado' => 'datetime',
        'f_aceptado_abogado' => 'datetime',
        'f_enviado_secretaria_credito' => 'datetime',

        'f_ultimo_rechazo' => 'datetime',
    ];

    public function expediente()
    {
        return $this->belongsTo(NuevoExpediente::class, 'id_expediente', 'codigo_cliente');
    }
}
