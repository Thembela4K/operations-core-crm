<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Submission extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_FINISHED = 'Finished';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_FINISHED,
    ];

    protected $fillable = [
        'assignment_id',
        'department_id',
        'submitted_by',
        'status',
        'notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function submittable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
