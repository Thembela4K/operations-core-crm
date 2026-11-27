<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An IT service desk ticket (report section 4).
 *
 * Any employee may raise one; the IT Officer triages and resolves. A ticket may
 * raise requisitions for procurement and stays a separate record from them.
 */
class Ticket extends Model
{
    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_UNDER_REVIEW = 'Under IT Review';

    public const STATUS_ASSIGNED = 'Assigned';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_AWAITING_REQUESTER = 'Awaiting Requester';

    public const STATUS_AWAITING_APPROVAL = 'Awaiting Approval';

    public const STATUS_AWAITING_PROCUREMENT = 'Awaiting Procurement';

    public const STATUS_RESOLVED = 'Resolved';

    public const STATUS_CLOSED = 'Closed';

    public const STATUS_REOPENED = 'Reopened';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_REQUESTER,
        self::STATUS_AWAITING_APPROVAL,
        self::STATUS_AWAITING_PROCUREMENT,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_REOPENED,
        self::STATUS_CANCELLED,
    ];

    /** Statuses that mean the ticket still needs attention. */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_REQUESTER,
        self::STATUS_AWAITING_APPROVAL,
        self::STATUS_AWAITING_PROCUREMENT,
        self::STATUS_REOPENED,
    ];

    public const PRIORITY_LOW = 'Low';

    public const PRIORITY_NORMAL = 'Normal';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITY_URGENT = 'Urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'ticket_number',
        'requester_id',
        'department_id',
        'ticket_category_id',
        'assigned_to',
        'created_by',
        'title',
        'description',
        'priority',
        'status',
        'resolution_note',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->latest();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->latest();
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'source_ticket_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Requesters see their own tickets; triage roles and the department see the
     * queue.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canTriageItTickets()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('requester_id', $user->id)
                ->orWhere('assigned_to', $user->id);
        });
    }
}
