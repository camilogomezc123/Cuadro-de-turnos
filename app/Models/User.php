<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password',
        'rol', 'uci_asignada', 'medico_id',
    ];

    public function medico()
    {
        return $this->belongsTo(\App\Models\Medico::class);
    }

    public function esMaster(): bool      { return $this->rol === 'master'; }
    public function esCoordinador(): bool { return in_array($this->rol, ['master', 'coordinador']); }
    public function esMedico(): bool      { return $this->rol === 'medico'; }
    public function puedeEditar(): bool   { return $this->esMaster(); }
    public function soloVeSusTurnos(): bool { return $this->rol === 'medico'; }

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
            'password' => 'hashed',
        ];
    }
}
