<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const STATUS_CLOCKED_IN = 'Clocked In';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_CORRECTED = 'Corrected';

    public const STATUS_PENDING_REVIEW = 'Pending Review';

    public const STATUSES = [
        self::STATUS_CLOCKED_IN,
        self::STATUS_COMPLETED,
        self::STATUS_CORRECTED,
        self::STATUS_PENDING_REVIEW,
    ];

    protected $fillable = [
        'user_id',
        'department_id',
        'work_date',
        'in_time',
        'out_time',
        'total_minutes',
        'status',
        'note',
        'correction_note',
        'corrected_by',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'in_time' => 'datetime',
            'out_time' => 'datetime',
            'corrected_at' => 'datetime',
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

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports() || $user->canManage()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function formattedDuration(): string
    {
        $hours = intdiv((int) $this->total_minutes, 60);
        $minutes = (int) $this->total_minutes % 60;

        return sprintf('%dh %02dm', $hours, $minutes);
    }
}
