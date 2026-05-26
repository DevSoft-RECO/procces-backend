<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'username',
        'email',
        'telefono',
        'id_agencia',
        'puesto',
        'avatar',
        'roles_list',
        'permissions_list',
        'jti',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['idagencia', 'roles', 'permissions', 'permisos'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'roles_list' => 'array',
        'permissions_list' => 'array',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'roles_list' => 'array',
            'permissions_list' => 'array',
        ];
    }

    // --- Accesores de Compatibilidad Histórica ---

    public function getIdagenciaAttribute()
    {
        return $this->id_agencia;
    }

    public function getRolesAttribute()
    {
        return $this->roles_list ?? [];
    }

    public function getPermissionsAttribute()
    {
        return $this->permissions_list ?? [];
    }

    public function getPermisosAttribute()
    {
        return $this->permissions_list ?? [];
    }

    /**
     * Relación física con la Agencia local.
     */
    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia');
    }

    // --- Transient Properties for SSO (Not saved in DB) ---
    public $agencia_data = null;

    // --- Authorization Helpers ---

    public function hasRole($role) {
        return is_array($this->roles_list) && in_array($role, $this->roles_list);
    }

    public function hasPermissionTo($permission) {
        if ($this->hasRole('Super Admin')) return true;

        return is_array($this->permissions_list) && in_array($permission, $this->permissions_list);
    }

    public function getAgenciaId() {
        return $this->id_agencia ?? ($this->agencia_data['id'] ?? null);
    }

    // En App\Models\User.php
public function bufete() {
    return $this->hasOne(Bufete::class, 'user_id');
}

    // --- Compatibility Helpers para Laravel Auth (BadMethodCallException Fix) ---

    /**
     * Retorna si el usuario tiene un permiso específico.
     * Laravel llama a este método internamente en contextos de API.
     */
    public function tokenCan($ability)
    {
        // Reutilizamos tu lógica de permisos existente
        return $this->hasPermissionTo($ability);
    }

    /**
     * Laravel a veces busca el token actual al autenticar.
     * Como usas JWT externo, retornamos null.
     */
    public function currentAccessToken()
    {
        return null;
    }
}
