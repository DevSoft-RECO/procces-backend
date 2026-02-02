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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    // --- Transient Properties for SSO (Not saved in DB) ---
    public $roles_list = [];
    public $permissions_list = [];
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
        return $this->agencia_data['id'] ?? null;
    }
}
