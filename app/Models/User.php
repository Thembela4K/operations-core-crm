<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_DIRECTOR = 'director';

    public const ROLE_RECEPTION = 'reception';

    public const ROLE_DEPARTMENT_USER = 'department_user';

    public const ROLE_BUSINESS_ADMIN = 'business_admin';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_DIRECTOR => 'Director',
        self::ROLE_RECEPTION => 'Reception',
        self::ROLE_DEPARTMENT_USER => 'Department User',
        self::ROLE_BUSINESS_ADMIN => 'Business Admin',
    ];

    /**
     * Which roles may enter each module.
     *
     * Modules absent from this map are personal (tasks, projects, attendance,
     * leave, notifications) and open to every active user.
     *
     * finance and approvals are deliberately closed to department users: money
     * and the approval queue belong to reception, direction and analysis.
     */
    public const MODULE_ACCESS = [
        'clients' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DEPARTMENT_USER,
        ],
        'catalog' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DEPARTMENT_USER,
        ],
        'operations' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DEPARTMENT_USER,
        ],
        'documents' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DEPARTMENT_USER,
        ],
        'finance' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
        ],
        'approvals' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_BUSINESS_ADMIN,
        ],
        'reports' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
        ],
        'users' => [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_BUSINESS_ADMIN,
        ],
        'admin' => [
            self::ROLE_SUPER_ADMIN,
        ],
    ];

    protected $fillable = [
        'staff_member_id',
        'department_id',
        'invited_by',
        'name',
        'username',
        'employee_no',
        'job_title',
        'email',
        'password',
        'role',
        'is_active',
        'receives_submissions',
        'can_access_sppra',
        'invited_at',
        'invitation_accepted_at',
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
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function crmNotifications(): HasMany
    {
        return $this->hasMany(CrmNotification::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canManage(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION);
    }

    public function canManageFinance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION);
    }

    public function canDraftFinance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION, self::ROLE_DEPARTMENT_USER);
    }

    public function canApproveFinance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canViewReports(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION, self::ROLE_BUSINESS_ADMIN);
    }

    public function canViewRequisitions(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION, self::ROLE_BUSINESS_ADMIN);
    }

    public function canApproveRequisitions(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canReleaseRequisitionFunds(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canFulfillRequisitions(): bool
    {
        return $this->canReleaseRequisitionFunds();
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
        return $this->canManage() || $this->canReviewSubmissions() || $this->hasRole(self::ROLE_BUSINESS_ADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isBusinessAdmin(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN);
    }

    /**
     * Business admins administer staff accounts (see and add users) but are
     * not technical super admins: they cannot delete accounts or touch a
     * super admin, and every other module stays read-only for them.
     */
    public function canManageUsers(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_BUSINESS_ADMIN);
    }

    public function canDeleteUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Roles this actor may assign when creating or editing a user.
     *
     * @return array<string, string>
     */
    public function assignableRoles(): array
    {
        $roles = self::ROLES;

        if (! $this->isSuperAdmin()) {
            unset($roles[self::ROLE_SUPER_ADMIN]);
        }

        return $roles;
    }

    /**
     * Central module gate. Modules not listed in MODULE_ACCESS are personal
     * and open to every active user.
     */
    public function canAccessModule(string $module): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $roles = self::MODULE_ACCESS[$module] ?? null;

        return $roles === null || in_array($this->role, $roles, true);
    }

    /**
     * Roles that may see every department's records.
     */
    public function seesAllDepartments(): bool
    {
        return $this->hasRole(
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
        );
    }

    /**
     * Who may see deadline pressure and workload across the whole company.
     *
     * Everyone else (department staff, reception) is limited to their own and
     * their department's work — never another department's.
     */
    public function canSeeCompanyDeadlines(): bool
    {
        return $this->hasRole(
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_BUSINESS_ADMIN,
        );
    }

    /**
     * Everyone who works inside a department and should read that department's
     * operational records, even when they may only edit their own.
     */
    public function canViewDepartmentRecords(): bool
    {
        return $this->seesAllDepartments() || $this->hasRole(self::ROLE_DEPARTMENT_USER);
    }

    /**
     * Billing secrets (VAT/tax number, billing mailbox) and money totals are
     * for company-scope roles only. Department staff see the client for work
     * context, not the commercial detail.
     */
    public function canViewClientFinancials(): bool
    {
        return $this->seesAllDepartments();
    }

    public function canManageAttendance(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_RECEPTION);
    }

    /**
     * Leave is approved by management, not by reception, so approval is kept
     * separate from attendance administration.
     */
    public function canApproveLeave(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canViewAllLeave(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    /**
     * Management and reporting roles may see every employee's projects.
     * Everyone else only sees their own.
     */
    public function canViewAllProjects(): bool
    {
        return $this->hasRole(
            self::ROLE_SUPER_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_BUSINESS_ADMIN,
        );
    }

    public function canAdministerProjects(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function displayLogin(): string
    {
        return $this->username ?: (string) $this->email;
    }
}
