<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who worked on the week's plan alongside the report owner: either an
 * employee picked from the staff list, or an outsourced person named directly.
 */
class WeekPlanReportContributor extends Model
{
    protected $fillable = [
        'week_plan_report_id',
        'user_id',
        'name',
        'organisation',
        'contribution',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(WeekPlanReport::class, 'week_plan_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->user?->name ?: ($this->name ?: 'Unnamed contributor');
    }

    public function isOutsourced(): bool
    {
        return $this->user_id === null;
    }
}
