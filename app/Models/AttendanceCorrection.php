<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A controlled request to change recorded times. The original clock events stay
 * intact; an approved correction is an additional audited action.
 */
class AttendanceCorrection extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'attendance_record_id',
        'requested_by',
        'original_in_at',
        'original_out_at',
        'proposed_in_at',
        'proposed_out_at',
        'reason',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'original_in_at' => 'datetime',
            'original_out_at' => 'datetime',
            'proposed_in_at' => 'datetime',
            'proposed_out_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
