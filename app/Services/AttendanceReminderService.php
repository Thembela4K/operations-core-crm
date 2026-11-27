<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the attendance reminders described in report 5.3 and 5.4.
 *
 * The rules are deliberately gentle: a morning nudge to record arrival, and a
 * clock-out nudge before the end of the scheduled day. Nothing is classified as
 * late and approved leave suppresses both.
 */
class AttendanceReminderService
{
    public const MORNING_WINDOW_START = '08:00';

    public const MORNING_WINDOW_END = '09:00';

    /**
     * Employees with no arrival recorded today who are not on approved leave.
     *
     * @return Collection<int, User>
     */
    public function morningReminderRecipients(?Carbon $date = null): Collection
    {
        $date ??= now();

        return $this->activeEmployees()
            ->reject(fn (User $user): bool => $this->onApprovedLeave($user, $date))
            ->reject(fn (User $user): bool => $this->recordFor($user, $date) !== null)
            ->values();
    }

    /**
     * Employees who clocked in but have not clocked out, once the reminder time
     * for their scheduled day has arrived.
     *
     * @return Collection<int, User>
     */
    public function clockOutReminderRecipients(?Carbon $date = null): Collection
    {
        $date ??= now();

        $schedule = AttendanceSchedule::forDate($date);

        if (! $schedule || $schedule->reminderAt($date) === null || $date->lt($schedule->reminderAt($date))) {
            return collect();
        }

        return $this->activeEmployees()
            ->reject(fn (User $user): bool => $this->onApprovedLeave($user, $date))
            ->filter(function (User $user) use ($date): bool {
                $record = $this->recordFor($user, $date);

                return $record !== null && $record->in_time !== null && $record->out_time === null;
            })
            ->values();
    }

    public function isMorningWindow(?Carbon $date = null): bool
    {
        $date ??= now();
        $start = $date->copy()->setTimeFromTimeString(self::MORNING_WINDOW_START);
        $end = $date->copy()->setTimeFromTimeString(self::MORNING_WINDOW_END);

        return $date->between($start, $end);
    }

    public function onApprovedLeave(User $user, ?Carbon $date = null): bool
    {
        $date ??= now();

        return LeaveApplication::query()
            ->where('user_id', $user->id)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Whether the employee has already been prompted to check in today.
     */
    public function alreadyPromptedToday(User $user, ?Carbon $date = null): bool
    {
        $date ??= now();

        return $user->attendance_prompted_on !== null
            && $user->attendance_prompted_on->toDateString() === $date->toDateString();
    }

    public function markPrompted(User $user, ?Carbon $date = null): void
    {
        $date ??= now();

        $user->forceFill(['attendance_prompted_on' => $date->toDateString()])->save();
    }

    public function hasArrivalToday(User $user, ?Carbon $date = null): bool
    {
        return $this->recordFor($user, $date ?? now()) !== null;
    }

    /**
     * @return Collection<int, User>
     */
    private function activeEmployees(): Collection
    {
        return User::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function recordFor(User $user, Carbon $date): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $date->toDateString())
            ->first();
    }
}
