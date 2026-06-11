<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientActivity extends Model
{
    public const TYPES = ['Call', 'Meeting', 'Email', 'Note', 'Follow-up', 'Site Visit', 'Other'];

    public const STATUSES = ['Open', 'Done', 'Cancelled'];

    protected $fillable = [
        'client_id',
        'responsible_user_id',
        'created_by',
        'type',
        'subject',
        'notes',
        'activity_date',
        'next_follow_up_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'next_follow_up_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canViewReports() || $user->canManageFinance()) {
            return $query;
        }

        return $query->where('responsible_user_id', $user->id);
    }
}
