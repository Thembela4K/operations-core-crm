<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    public const TYPE_ANNUAL = 'Annual Leave';

    public const TYPE_SICK = 'Sick Leave';

    public const TYPE_MATERNITY = 'Maternity Leave';

    public const TYPE_STUDY = 'Study Leave';

    public const TYPE_FAMILY = 'Family Responsibility / Compassionate';

    public const TYPE_INJURY = 'Injury on Duty (COIDA)';

    public const TYPE_UNAUTHORISED = 'Unauthorised Absence';

    public const TYPE_TIME_NOT_WORKED = 'Time Not Worked';

    public const TYPE_OTHER = 'Other';

    /**
     * Mirrors the DATF025 leave application form: each category, whether it
     * draws down the annual balance, whether supporting proof is required,
     * and the note printed on the paper form for that section.
     */
    public const TYPES = [
        self::TYPE_ANNUAL => [
            'label' => 'Annual Leave',
            'deducts' => true,
            'certificate' => false,
            'note' => 'Deducted from the annual working-day balance.',
        ],
        self::TYPE_SICK => [
            'label' => 'Sick Leave',
            'deducts' => false,
            'certificate' => true,
            'note' => 'Attach a medical certificate when more than 2 working days are taken. The certificate should cover the full period of absence.',
        ],
        self::TYPE_MATERNITY => [
            'label' => 'Maternity Leave',
            'deducts' => false,
            'certificate' => true,
            'note' => 'Attach a medical certificate reflecting the probable due date.',
        ],
        self::TYPE_STUDY => [
            'label' => 'Study Leave',
            'deducts' => false,
            'certificate' => true,
            'note' => 'Attach notice of the date of examination (approved courses only).',
        ],
        self::TYPE_FAMILY => [
            'label' => 'Family Responsibility / Compassionate',
            'deducts' => false,
            'certificate' => true,
            'note' => 'Proof of reason may be requested.',
        ],
        self::TYPE_INJURY => [
            'label' => 'Injury on Duty (COIDA)',
            'deducts' => false,
            'certificate' => true,
            'note' => 'Attach a medical certificate and the accident report.',
        ],
        self::TYPE_UNAUTHORISED => [
            'label' => 'Unauthorised Absence',
            'deducts' => false,
            'certificate' => false,
            'note' => 'Value deducted off salary. Not deducted from leave.',
        ],
        self::TYPE_TIME_NOT_WORKED => [
            'label' => 'Time Not Worked',
            'deducts' => false,
            'certificate' => false,
            'note' => 'To be deducted off salary. Not deducted from leave.',
        ],
        self::TYPE_OTHER => [
            'label' => 'Other',
            'deducts' => false,
            'certificate' => false,
            'note' => 'Not deducted from leave.',
        ],
    ];

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'department_id',
        'leave_type',
        'from_date',
        'to_date',
        'working_days',
        'back_at_work_on',
        'reason',
        'certificate_attached',
        'attachment_path',
        'status',
        'leave_year',
        'applied_on',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'back_at_work_on' => 'date',
            'applied_on' => 'date',
            'decided_at' => 'datetime',
            'certificate_attached' => 'boolean',
            'working_days' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->leave_type]['label'] ?? $this->leave_type;
    }

    public function deductsFromBalance(): bool
    {
        return (bool) (self::TYPES[$this->leave_type]['deducts'] ?? false);
    }

    public function requiresCertificate(): bool
    {
        return (bool) (self::TYPES[$this->leave_type]['certificate'] ?? false);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeDecided(): bool
    {
        return $this->isPending();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewAllLeave()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
