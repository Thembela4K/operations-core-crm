<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Assignment extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Unassigned',
        'Assigned',
        'Assignment Email Sent',
        'Assignment Email Failed',
    ];

    public const WORKFLOW_STATUSES = [
        'Assigned',
        'Accepted',
        'In Progress',
        'Draft Submitted',
        'Finished Submitted',
        'Completed',
        'Cancelled',
    ];

    /** Workflow states that still need the assignee to act. */
    public const OPEN_STATUSES = [
        'Assigned',
        'Accepted',
        'In Progress',
        'Draft Submitted',
        'Finished Submitted',
    ];

    public const KIND_WORK = 'work';

    public const KIND_MEETING = 'meeting';

    public const KINDS = [self::KIND_WORK, self::KIND_MEETING];

    protected $fillable = [
        'assignable_type',
        'assignable_id',
        'title',
        'kind',
        'department_id',
        'assigned_user_id',
        'assignee_name',
        'assignee_email',
        'status',
        'workflow_status',
        'assigned_at',
        'due_date',
        'starts_at',
        'ends_at',
        'location',
        'accepted_at',
        'instructions',
        'read_at',
        'viewed_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'accepted_at' => 'datetime',
            'due_date' => 'date',
            'read_at' => 'datetime',
            'viewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssignmentEvent::class)->latest();
    }

    public function isMeeting(): bool
    {
        return $this->kind === self::KIND_MEETING;
    }

    public function isOpen(): bool
    {
        return in_array($this->workflow_status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date !== null && $this->due_date->isPast();
    }

    /**
     * A readable label for the assigned work.
     */
    public function label(): string
    {
        return $this->title
            ?: ($this->assignable?->title ?? $this->assignable?->opportunity ?? 'Assignment #'.$this->id);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('assigned_user_id', $user->id);
    }

    /**
     * Assignments a person may see: their own, plus everything for the roles
     * that oversee operations.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(User::ROLE_BUSINESS_ADMIN, User::ROLE_DIRECTOR, User::ROLE_RECEPTION) || $user->isDepartmentHead()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('assigned_user_id', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    public function scopeMeetingsToday(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_MEETING)
            ->whereIn('workflow_status', self::OPEN_STATUSES)
            ->whereNotNull('starts_at')
            ->whereDate('starts_at', now()->toDateString());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('workflow_status', self::OPEN_STATUSES)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }
}
