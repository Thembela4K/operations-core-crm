<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly report and plan written by an employee: how far last week's tasks
 * got, what they are doing this week, and who contributed.
 */
class WeekPlanReport extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SUBMITTED];

    public const SECTION_LAST_WEEK = 'last_week';

    public const SECTION_THIS_WEEK = 'this_week';

    protected $fillable = [
        'user_id',
        'department_id',
        'week_start',
        'week_end',
        'last_week_progress',
        'this_week_plan',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'submitted_at' => 'datetime',
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

    public function tasks(): HasMany
    {
        return $this->hasMany(WeekPlanReportTask::class)->orderBy('section')->orderBy('position');
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(WeekPlanReportContributor::class);
    }

    public function lastWeekTasks(): HasMany
    {
        return $this->tasks()->where('section', self::SECTION_LAST_WEEK);
    }

    public function thisWeekTasks(): HasMany
    {
        return $this->tasks()->where('section', self::SECTION_THIS_WEEK);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports()) {
            return $query;
        }

        if ($user->isDepartmentHead()) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->where('user_id', $user->id);
    }
}
