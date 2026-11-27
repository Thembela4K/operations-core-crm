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

    public const ROLE_DIRECTOR = 'director';

    public const ROLE_RECEPTION = 'reception';

    public const ROLE_DEPARTMENT_USER = 'department_user';

    /**
     * The top role: formerly "super admin". One administrator role owns the
     * system, so there is no separate super-admin account to maintain.
     */
    public const ROLE_BUSINESS_ADMIN = 'business_admin';

    /**
     * Seniority inside a department. The role stays department_user; the level
     * says whether someone is a trainee, an intern, an officer or a head.
     */
    public const LEVEL_TRAINEE = 'Trainee';

    public const LEVEL_INTERN = 'Intern';

    public const LEVEL_OFFICER = 'Officer';

    public const LEVEL_HEAD = 'Head';

    public const LEVELS = [
        self::LEVEL_TRAINEE,
        self::LEVEL_INTERN,
        self::LEVEL_OFFICER,
        self::LEVEL_HEAD,
    ];

    /** Levels a departmental officer may create. */
    public const JUNIOR_LEVELS = [
        self::LEVEL_TRAINEE,
        self::LEVEL_INTERN,
    ];

    public const ROLES = [
        self::ROLE_BUSINESS_ADMIN => 'Business Admin',
        self::ROLE_DIRECTOR => 'Director',
        self::ROLE_RECEPTION => 'Front Desk Admin',
        self::ROLE_DEPARTMENT_USER => 'Department User',
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
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_DEPARTMENT_USER,
        ],
        'catalog' => [
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_DEPARTMENT_USER,
        ],
        'operations' => [
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_DEPARTMENT_USER,
        ],
        'documents' => [
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
            self::ROLE_DEPARTMENT_USER,
        ],
        'finance' => [
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
        ],
        'approvals' => [
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
        ],
        'reports' => [
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
        ],
        'users' => [
            self::ROLE_BUSINESS_ADMIN,
        ],
        'admin' => [
            self::ROLE_BUSINESS_ADMIN,
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
        'level',
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
            'attendance_prompted_on' => 'date',
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
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_RECEPTION);
    }

    public function canManageFinance(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_RECEPTION);
    }

    public function canDraftFinance(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_RECEPTION, self::ROLE_DEPARTMENT_USER);
    }

    public function canApproveFinance(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canViewReports(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canViewRequisitions(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canApproveRequisitions(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canReleaseRequisitionFunds(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canFulfillRequisitions(): bool
    {
        return $this->canReleaseRequisitionFunds();
    }

    public function canReviewSubmissions(): bool
    {
        return $this->receives_submissions
            || $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canAccessSppra(): bool
    {
        return $this->can_access_sppra
            || $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    public function canViewPortfolio(): bool
    {
        return $this->canManage() || $this->canReviewSubmissions() || $this->hasRole(self::ROLE_BUSINESS_ADMIN);
    }

    public function isBusinessAdmin(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN);
    }

    public function isOfficer(): bool
    {
        return $this->hasRole(self::ROLE_DEPARTMENT_USER) && $this->level === self::LEVEL_OFFICER;
    }

    public function isDepartmentHead(): bool
    {
        return $this->hasRole(self::ROLE_DEPARTMENT_USER) && $this->level === self::LEVEL_HEAD;
    }

    /**
     * The administrator role owns staff accounts.
     */
    public function canManageUsers(): bool
    {
        return $this->isBusinessAdmin();
    }

    /**
     * Departmental officers and heads may add trainees and interns to their own
     * department without a business admin doing it for them.
     */
    public function canCreateTeamMembers(): bool
    {
        return $this->isBusinessAdmin() || $this->isOfficer() || $this->isDepartmentHead();
    }

    /**
     * Company documents (trading licences, registrations, policies) are
     * uploaded and maintained by the business admin and directors; everyone
     * else may only read and download them.
     */
    public function canManageCompanyDocuments(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canDeleteUsers(): bool
    {
        return $this->isBusinessAdmin();
    }

    /**
     * The IT Officer is a department user inside the IT Department. Every
     * employee raises tickets; the IT Officer receives them, triages them and
     * turns procurement ones into requisitions addressed to the directors.
     *
     * The business admin keeps an override so the system owner is never locked
     * out of a stuck queue.
     */
    public function canTriageItTickets(): bool
    {
        if ($this->isBusinessAdmin()) {
            return true;
        }

        return $this->hasRole(self::ROLE_DEPARTMENT_USER)
            && (bool) $this->department?->is_it_department;
    }

    public function isItOfficer(): bool
    {
        return $this->hasRole(self::ROLE_DEPARTMENT_USER)
            && (bool) $this->department?->is_it_department;
    }

    /**
     * Levels this actor may assign to a new team member.
     *
     * @return array<int, string>
     */
    public function assignableLevels(): array
    {
        return $this->isBusinessAdmin() ? self::LEVELS : self::JUNIOR_LEVELS;
    }

    /**
     * Roles this actor may assign when creating or editing a user.
     *
     * @return array<string, string>
     */
    public function assignableRoles(): array
    {
        if ($this->isBusinessAdmin()) {
            return self::ROLES;
        }

        // Officers and heads may only ever create department members.
        return [self::ROLE_DEPARTMENT_USER => self::ROLES[self::ROLE_DEPARTMENT_USER]];
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
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
        );
    }

    /**
     * Who may see deadline pressure and workload across the whole company.
     *
     * This drives the *workload* aggregate only. Deadline pressure itself is a
     * personal list — see deadlinesArePersonalOnly().
     */
    public function canSeeCompanyDeadlines(): bool
    {
        return $this->hasRole(
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
        );
    }

    /**
     * Management roles only ever see their own deadlines — never a colleague's,
     * including another director's. They still see everyone's workload.
     */
    public function deadlinesArePersonalOnly(): bool
    {
        return $this->hasRole(
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
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
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_RECEPTION);
    }

    /**
     * Leave is approved by management, not by reception, so approval is kept
     * separate from attendance administration.
     */
    public function canApproveLeave(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR);
    }

    public function canViewAllLeave(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN, self::ROLE_DIRECTOR, self::ROLE_RECEPTION);
    }

    /**
     * Management and reporting roles may see every employee's projects.
     * Everyone else only sees their own.
     */
    public function canViewAllProjects(): bool
    {
        return $this->hasRole(
            self::ROLE_BUSINESS_ADMIN,
            self::ROLE_DIRECTOR,
            self::ROLE_RECEPTION,
        );
    }

    public function canAdministerProjects(): bool
    {
        return $this->hasRole(self::ROLE_BUSINESS_ADMIN);
    }

    public function displayLogin(): string
    {
        return $this->username ?: (string) $this->email;
    }
}
