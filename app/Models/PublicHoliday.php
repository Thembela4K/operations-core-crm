<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PublicHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Set of `Y-m-d` strings for the given year, used by the working-day count.
     *
     * @return Collection<string, string>
     */
    public static function datesForYear(int $year): Collection
    {
        return self::query()
            ->active()
            ->whereYear('holiday_date', $year)
            ->pluck('name', 'holiday_date')
            ->mapWithKeys(fn ($name, $date): array => [
                Carbon::parse($date)->toDateString() => $name,
            ]);
    }
}
