<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Expected working day per weekday, used for the end-of-day reminder.
 */
class AttendanceSchedule extends Model
{
    protected $fillable = [
        'name',
        'weekday',
        'start_time',
        'end_time',
        'is_working_day',
    ];

    protected function casts(): array
    {
        return [
            'is_working_day' => 'boolean',
        ];
    }

    /**
     * The schedule row for a given date (1 = Monday ... 7 = Sunday).
     */
    public static function forDate(Carbon $date): ?self
    {
        return static::query()->where('weekday', $date->isoWeekday())->first();
    }

    /**
     * When the clock-out reminder should fire: ten minutes before the end of
     * the scheduled working day.
     */
    public function reminderAt(Carbon $date): ?Carbon
    {
        if (! $this->is_working_day || ! $this->end_time) {
            return null;
        }

        return $date->copy()->startOfDay()
            ->addSeconds($this->secondsOfDay((string) $this->end_time))
            ->subMinutes(10);
    }

    private function secondsOfDay(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours * 3600) + ((int) $minutes * 60);
    }
}
