<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'due_on',
        'days_before',
        'recipient',
        'status',
        'message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
