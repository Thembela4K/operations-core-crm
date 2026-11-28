<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlement extends Model
{
    /**
     * Standard annual leave allowance, in working days, per calendar year.
     */
    public const DEFAULT_ANNUAL_DAYS = 12;

    protected $fillable = [
        'user_id',
        'year',
        'entitlement_days',
        'carry_over_days',
        'adjustment_days',
    ];

    protected function casts(): array
    {
        return [
            'entitlement_days' => 'float',
            'carry_over_days' => 'float',
            'adjustment_days' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns the entitlement row for a user and year, creating the default
     * 12-day allowance the first time it is requested.
     */
    public static function forUser(User $user, int $year): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            ['entitlement_days' => self::DEFAULT_ANNUAL_DAYS],
        );
    }

    public function totalDays(): float
    {
        return (float) $this->entitlement_days
            + (float) $this->carry_over_days
            + (float) $this->adjustment_days;
    }
}
