<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmTask extends Model
{
    public const STATUS_TO_DO = 'To Do';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_BLOCKED = 'Blocked';

    public const STATUS_DONE = 'Done';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_TO_DO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    protected $table = 'crm_tasks';

    protected $fillable = [
        'taskable_type',
        'taskable_id',
        'department_id',
        'assigned_to',
        'created_by',
        'task_number',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
        return $this->hasMany(TaskComment::class)->orderByDesc('created_at');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports() || $user->canManage()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('department_id', $user->department_id)
                ->orWhere('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_CANCELLED], true);
    }
}
