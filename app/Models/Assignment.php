<?php

namespace App\Models;

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
        'In Progress',
        'Draft Submitted',
        'Finished Submitted',
    ];

    protected $fillable = [
        'department_id',
        'assigned_user_id',
        'assignee_name',
        'assignee_email',
        'status',
        'workflow_status',
        'assigned_at',
        'due_date',
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
}
