<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Quotation extends Model
{
    use HasFactory;

    public const STATUS_OVERDUE = 'Overdue';

    public const STATUSES = [
        'Draft',
        'Sent',
        'Under Review',
        'Draft Submitted',
        'Finished Submitted',
        self::STATUS_OVERDUE,
        'Accepted',
        'Rejected',
        'Expired',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    public const RISKS = ['Low', 'Medium', 'High'];

    protected $fillable = [
        'quotation_code',
        'client',
        'opportunity',
        'owner',
        'owner_email',
        'status',
        'priority',
        'rating',
        'risk',
        'win_probability_percent',
        'quoted_amount',
        'expected_cost',
        'issue_date',
        'valid_until',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'win_probability_percent' => 'integer',
            'quoted_amount' => 'decimal:2',
            'expected_cost' => 'decimal:2',
            'issue_date' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function assignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignable');
    }

    public function latestAssignment(): MorphOne
    {
        return $this->morphOne(Assignment::class, 'assignable')->latestOfMany();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'emailable');
    }

    public function reminderLogs(): MorphMany
    {
        return $this->morphMany(ReminderLog::class, 'remindable');
    }

    public function submissions(): MorphMany
    {
        return $this->morphMany(Submission::class, 'submittable');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewPortfolio()) {
            return $query;
        }

        return $query->whereHas('assignments', function (Builder $assignmentQuery) use ($user): void {
            $assignmentQuery->where('department_id', $user->department_id);
        });
    }
}
