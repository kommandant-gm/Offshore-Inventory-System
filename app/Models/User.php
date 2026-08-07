<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AccessMatrix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @var list<string> */
    private const MIRI_ALLOWED_USERNAMES = [
        'mariesim', 'duyan', 'patrickleong', 'leekp', 'christopher', 'alexleong',
        'terrencelim', 'gevrina', 'dywan', 'frankypilai', 'suhaileysuhailim',
    ];
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'department',
        'job_title',
        'directory_active',
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
            'directory_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return blank($this->role) || $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return strtolower((string) $this->username) === 'codex';
    }

    public function canAccessSystem(): bool
    {
        if ($this->directory_active === false) return false;
        if ($this->isSuperAdmin()) return true;
        if (in_array($this->role, ['admin', 'it', 'miri', 'supervisor', 'technician'], true)) return true;

        $username = strtolower(trim((string) $this->username));
        if (in_array($username, self::MIRI_ALLOWED_USERNAMES, true)) return true;

        $department = strtoupper(preg_replace('/\s+/', ' ', trim((string) $this->department)));
        return $department === 'IT & DIGITAL';
    }

    public function isMiriRestrictedUser(): bool
    {
        return self::isMiriUsername($this->username);
    }

    public static function isMiriUsername(?string $username): bool
    {
        return in_array(strtolower(trim((string) $username)), self::MIRI_ALLOWED_USERNAMES, true);
    }

    public function isItDigitalUser(): bool
    {
        $department = strtoupper(preg_replace('/\s+/', ' ', trim((string) $this->department)));
        return $this->role === 'it' || $department === 'IT & DIGITAL';
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
        if ($this->permissionLevel($module) !== AccessMatrix::EDIT) {
            return false;
        }

        $context = app(\App\Services\BranchContext::class);
        $branchId = $context->id($this);

        return $branchId === null || $context->canEdit($this, $branchId);
    }

    public function resolvedPermissions(): array
    {
        if (blank($this->role)) {
            return AccessMatrix::permissionsForRole('admin');
        }

        return AccessMatrix::normalizePermissions($this->permissions, $this->role);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['access_level', 'is_default'])
            ->withTimestamps();
    }
}
