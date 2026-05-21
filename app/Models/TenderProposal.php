<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TenderProposal extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Draft',
        'Assigned',
        'In Progress',
        'Draft Submitted',
        'Finished Submitted',
        'On Hold',
        'Closed',
        'Cancelled',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    public const RISKS = ['Low', 'Medium', 'High'];

    protected $fillable = [
        'tender_reference',
        'title',
        'owner',
        'owner_email',
        'status',
        'priority',
        'rating',
        'risk',
        'progress_percent',
        'budget',
        'received_date',
        'closing_date',
        'brief',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'budget' => 'decimal:2',
            'progress_percent' => 'integer',
            'received_date' => 'date',
            'closing_date' => 'date',
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

    public function importantDates(): HasMany
    {
        return $this->hasMany(ImportantDate::class)->orderBy('due_date');
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
