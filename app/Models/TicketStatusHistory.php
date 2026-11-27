<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable status transition log for a ticket (report 4.8).
 */
class TicketStatusHistory extends Model
{
    protected $fillable = [
        'ticket_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
