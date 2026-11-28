<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of what actually happened. Corrections never rewrite these.
 */
class AttendanceEvent extends Model
{
    public const TYPE_CLOCK_IN = 'clock_in';

    public const TYPE_CLOCK_OUT = 'clock_out';

    public const TYPE_TYPE_CHANGED = 'type_changed';

    public const TYPE_CORRECTION = 'correction';

    protected $fillable = [
        'attendance_record_id',
        'user_id',
        'event_type',
        'attendance_type',
        'occurred_at',
        'actor_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
