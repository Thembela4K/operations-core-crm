<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in an assignment's life: assigned, accepted, started, submitted,
 * completed, cancelled or reassigned.
 */
class AssignmentEvent extends Model
{
    protected $fillable = [
        'assignment_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'notes',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
