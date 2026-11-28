<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeekPlanReportTask extends Model
{
    protected $fillable = [
        'week_plan_report_id',
        'task_id',
        'title',
        'section',
        'progress_percent',
        'planned_start',
        'planned_end',
        'carried_over',
        'notes',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'carried_over' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WeekPlanReport::class, 'week_plan_report_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CrmTask::class);
    }

    /**
     * Days the item is expected to take, inclusive of both dates.
     */
    public function estimatedDays(): ?int
    {
        if ($this->planned_start === null || $this->planned_end === null) {
            return null;
        }

        return (int) $this->planned_start->diffInDays($this->planned_end) + 1;
    }
}
