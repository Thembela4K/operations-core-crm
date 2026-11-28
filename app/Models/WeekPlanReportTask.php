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
        'carried_over',
        'notes',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
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
}
