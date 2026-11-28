<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    public const STATUS_NOT_STARTED = 'Not Started';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_ON_HOLD = 'On Hold';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Statuses that mean work is finished; any other status clears completed_at.
     */
    public const CLOSED_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'department_id',
        'created_by',
        'title',
        'description',
        'status',
        'progress_percent',
        'comment',
        'planned_date',
        'by_whom',
        'time_frame',
        'started_on',
        'target_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'started_on' => 'date',
            'target_date' => 'date',
            'completed_at' => 'datetime',
            'progress_percent' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->seesAllDepartments()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('user_id', $user->id);

            if ($user->department_id !== null) {
                $inner->orWhere('department_id', $user->department_id);
            }
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED_STATUSES);
    }
}
