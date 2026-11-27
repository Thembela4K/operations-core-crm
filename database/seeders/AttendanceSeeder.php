<?php

namespace Database\Seeders;

use App\Models\AttendanceSchedule;
use App\Models\AttendanceType;
use Illuminate\Database\Seeder;

/**
 * Attendance types and the standard working week (report 5.5 and 5.9).
 *
 * Office is the single default; the other types are selectable.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['Office', 'Normal attendance at company premises.', true],
            ['Fieldwork', 'Client-site work, surveys, inspections, GIS assignments and other field activities.', false],
            ['Remote', 'Work away from the office with permission.', false],
            ['Official Off-site', 'Meetings, workshops, training, conferences or other authorised external duties.', false],
        ];

        foreach ($types as [$name, $description, $isDefault]) {
            AttendanceType::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_default' => $isDefault, 'is_active' => true],
            );
        }

        // Only one active default may exist.
        AttendanceType::query()
            ->where('name', '!=', 'Office')
            ->update(['is_default' => false]);

        $schedule = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        foreach ($schedule as $weekday => $name) {
            $isFriday = $weekday === 5;
            $isWeekend = $weekday >= 6;

            AttendanceSchedule::query()->updateOrCreate(
                ['weekday' => $weekday],
                [
                    'name' => $name,
                    'start_time' => $isWeekend ? null : '08:00:00',
                    'end_time' => $isWeekend ? null : ($isFriday ? '16:00:00' : '17:00:00'),
                    'is_working_day' => ! $isWeekend,
                ],
            );
        }
    }
}
