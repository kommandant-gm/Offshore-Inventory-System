<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AccessMatrix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'permissions',
        'password',
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
            'permissions' => 'array',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return blank($this->role) || $this->role === 'admin';
    }

    public function permissionLevel(string $module): string
    {
        if ($this->isAdmin()) {
            return AccessMatrix::EDIT;
        }

        return AccessMatrix::normalizePermissions($this->permissions, $this->role)[$module] ?? AccessMatrix::NONE;
    }

    public function canRead(string $module): bool
    {
        return in_array($this->permissionLevel($module), [AccessMatrix::READ, AccessMatrix::EDIT], true);
    }

    public function canEdit(string $module): bool
    {
        return $this->permissionLevel($module) === AccessMatrix::EDIT;
    }

    public function resolvedPermissions(): array
    {
        if (blank($this->role)) {
            return AccessMatrix::permissionsForRole('admin');
        }

        return AccessMatrix::normalizePermissions($this->permissions, $this->role);
    }
}
