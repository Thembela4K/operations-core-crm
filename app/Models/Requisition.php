<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Requisition extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_IN_REVIEW = 'In Review';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_FUNDS_RELEASED = 'Funds Released';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_FUNDS_RELEASED,
        self::STATUS_CANCELLED,
    ];

    public const CATEGORIES = [
        'Operational',
        'Office',
        'Hardware',
        'Software',
        'Travel',
        'Client Work',
        'Tender Support',
        'Other',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    protected $fillable = [
        'department_id',
        'supplier_id',
        'requested_by',
        'approved_by',
        'released_by',
        'requisition_number',
        'addressed_to',
        'title',
        'category',
        'priority',
        'status',
        'needed_by',
        'estimated_total',
        'bank_total',
        'cash_total',
        'other_total',
        'purpose',
        'notes',
        'decision_notes',
        'release_notes',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'funds_released_at',
    ];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'estimated_total' => 'decimal:2',
            'bank_total' => 'decimal:2',
            'cash_total' => 'decimal:2',
            'other_total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'funds_released_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class)->orderBy('position');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'emailable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewRequisitions()) {
            return $query;
        }

        return $query->where('department_id', $user->department_id);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }
}
