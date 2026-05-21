<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_DIRECTOR = 'director';

    public const ROLE_RECEPTION = 'reception';

    public const ROLE_DEPARTMENT_USER = 'department_user';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_DIRECTOR => 'Director',
        self::ROLE_RECEPTION => 'Reception',
        self::ROLE_DEPARTMENT_USER => 'Department User',
    ];

    protected $fillable = [
        'department_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'receives_submissions',
        'can_access_sppra',
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
            'is_active' => 'boolean',
            'receives_submissions' => 'boolean',
            'can_access_sppra' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canManage(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER);
    }

    public function canReviewSubmissions(): bool
    {
        return $this->receives_submissions
            || $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canAccessSppra(): bool
    {
        return $this->can_access_sppra
            || $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canViewPortfolio(): bool
    {
        return $this->canManage() || $this->canReviewSubmissions();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }
}
