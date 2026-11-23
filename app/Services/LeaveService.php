<?php

namespace App\Services;

use App\Models\CrmNotification;
use App\Models\LeaveApplication;
use App\Models\LeaveEntitlement;
use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * Per-instance cache of holidays keyed by year, so repeated working-day
     * calculations in one request do not re-query. Deliberately not static:
     * a static cache would leak between tests and long-lived workers.
     *
     * @var array<int, array<string, string>>
     */
    private array $holidayCache = [];
    /**
     * Count of working days between two dates inclusive.
     *
     * Weekends and active public holidays are excluded, because the leave
     * allowance is expressed in working days, not calendar days.
     */
    public function workingDays(CarbonInterface $from, CarbonInterface $to): float
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lt($start)) {
            return 0.0;
        }

        $holidays = $this->holidaysBetween($start, $end);
        $days = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            if (isset($holidays[$day->toDateString()])) {
                continue;
            }

            $days++;
        }

        return (float) $days;
    }

    /**
     * The next day that is neither a weekend nor a public holiday.
     */
    public function nextWorkingDay(CarbonInterface $after): Carbon
    {
        $day = Carbon::parse($after)->startOfDay()->addDay();

        while (true) {
            $holidays = $this->holidaysFor($day->year);

            if (! $day->isWeekend() && ! isset($holidays[$day->toDateString()])) {
                return $day;
            }

            $day->addDay();
        }
    }

    /**
     * Annual leave position for a user in a calendar year.
     *
     * @return array{year:int,entitlement:float,carry_over:float,adjustment:float,total:float,taken:float,pending:float,remaining:float,available:float}
     */
    public function balanceFor(User $user, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;

        $entitlement = LeaveEntitlement::forUser($user, $year);
        $total = $entitlement->totalDays();

        $taken = $this->annualDays($user, $year, LeaveApplication::STATUS_APPROVED);
        $pending = $this->annualDays($user, $year, LeaveApplication::STATUS_PENDING);

        $remaining = round($total - $taken, 1);

        return [
            'year' => $year,
            'entitlement' => (float) $entitlement->entitlement_days,
            'carry_over' => (float) $entitlement->carry_over_days,
            'adjustment' => (float) $entitlement->adjustment_days,
            'total' => round($total, 1),
            'taken' => round($taken, 1),
            'pending' => round($pending, 1),
            'remaining' => $remaining,
            'available' => round($remaining - $pending, 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function apply(User $user, array $data): LeaveApplication
    {
        $from = Carbon::parse($data['from_date'])->startOfDay();
        $to = Carbon::parse($data['to_date'])->startOfDay();
        $type = (string) $data['leave_type'];

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => 'The last day cannot be before the first day.',
            ]);
        }

        if ($this->overlapsExistingLeave($user, $from, $to)) {
            throw ValidationException::withMessages([
                'from_date' => 'This period overlaps leave that is already pending or approved for this employee.',
            ]);
        }

        $workingDays = $this->workingDays($from, $to);

        if ($workingDays <= 0) {
            throw ValidationException::withMessages([
                'from_date' => 'The selected period contains no working days (weekends and public holidays only).',
            ]);
        }

        $deducts = (bool) (LeaveApplication::TYPES[$type]['deducts'] ?? false);

        if ($deducts) {
            $year = (int) $from->year;
            $balance = $this->balanceFor($user, $year);

            if ($workingDays > $balance['available']) {
                throw ValidationException::withMessages([
                    'from_date' => sprintf(
                        'This request needs %s working day(s) but only %s remain%s for %d.',
                        $this->formatDays($workingDays),
                        $this->formatDays($balance['available']),
                        $balance['pending'] > 0 ? ' (after '.$this->formatDays($balance['pending']).' already pending)' : '',
                        $year,
                    ),
                ]);
            }
        }

        $leave = DB::transaction(function () use ($user, $data, $from, $type, $workingDays): LeaveApplication {
            $leave = LeaveApplication::query()->create([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'leave_type' => $type,
                'from_date' => $from->toDateString(),
                'to_date' => $data['to_date'],
                'working_days' => $workingDays,
                'back_at_work_on' => $data['back_at_work_on']
                    ?? $this->nextWorkingDay(Carbon::parse($data['to_date']))->toDateString(),
                'reason' => $data['reason'] ?? null,
                'certificate_attached' => (bool) ($data['certificate_attached'] ?? false),
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => LeaveApplication::STATUS_PENDING,
                'leave_year' => (int) $from->year,
                'applied_on' => now()->toDateString(),
            ]);

            $this->notifyApprovers($leave);

            return $leave;
        });

        return $leave;
    }

    /**
     * @throws ValidationException
     */
    public function approve(LeaveApplication $leave, User $approver, ?string $note = null): LeaveApplication
    {
        return $this->decide($leave, $approver, LeaveApplication::STATUS_APPROVED, $note);
    }

    /**
     * @throws ValidationException
     */
    public function reject(LeaveApplication $leave, User $approver, ?string $note = null): LeaveApplication
    {
        return $this->decide($leave, $approver, LeaveApplication::STATUS_REJECTED, $note);
    }

    /**
     * @throws ValidationException
     */
    public function cancel(LeaveApplication $leave, User $actor): LeaveApplication
    {
        if (! $leave->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending application can be cancelled.',
            ]);
        }

        $leave->update([
            'status' => LeaveApplication::STATUS_CANCELLED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => 'Cancelled by '.$actor->name,
        ]);

        return $leave;
    }

    /**
     * @throws ValidationException
     */
    private function decide(LeaveApplication $leave, User $approver, string $status, ?string $note): LeaveApplication
    {
        if (! $leave->canBeDecided()) {
            throw ValidationException::withMessages([
                'status' => 'This application has already been '.strtolower($leave->status).'.',
            ]);
        }

        if ($leave->user_id === $approver->id) {
            throw ValidationException::withMessages([
                'status' => 'You cannot decide your own leave application.',
            ]);
        }

        // Re-check the balance at approval time: another request may have been
        // approved between submission and this decision.
        if ($status === LeaveApplication::STATUS_APPROVED && $leave->deductsFromBalance()) {
            $balance = $this->balanceFor($leave->user, $leave->leave_year);

            if ($leave->working_days > $balance['available']) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Cannot approve: only %s working day(s) remain for %d.',
                        $this->formatDays($balance['available']),
                        $leave->leave_year,
                    ),
                ]);
            }
        }

        $leave->update([
            'status' => $status,
            'decided_by' => $approver->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $this->notifyEmployee($leave);

        return $leave;
    }

    public function overlapsExistingLeave(User $user, CarbonInterface $from, CarbonInterface $to): bool
    {
        return LeaveApplication::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [LeaveApplication::STATUS_PENDING, LeaveApplication::STATUS_APPROVED])
            ->whereDate('from_date', '<=', Carbon::parse($to)->toDateString())
            ->whereDate('to_date', '>=', Carbon::parse($from)->toDateString())
            ->exists();
    }

    private function annualDays(User $user, int $year, string $status): float
    {
        return (float) LeaveApplication::query()
            ->where('user_id', $user->id)
            ->where('leave_type', LeaveApplication::TYPE_ANNUAL)
            ->where('leave_year', $year)
            ->where('status', $status)
            ->sum('working_days');
    }

    /**
     * @return array<string, string>  date => holiday name
     */
    private function holidaysBetween(Carbon $start, Carbon $end): array
    {
        $holidays = [];

        for ($year = $start->year; $year <= $end->year; $year++) {
            $holidays += $this->holidaysFor($year);
        }

        return $holidays;
    }

    /**
     * @return array<string, string>
     */
    private function holidaysFor(int $year): array
    {
        return $this->holidayCache[$year] ??= PublicHoliday::datesForYear($year)->all();
    }

    private function notifyApprovers(LeaveApplication $leave): void
    {
        $approvers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_DIRECTOR])
            ->get();

        foreach ($approvers as $approver) {
            if ($approver->id === $leave->user_id) {
                continue;
            }

            CrmNotification::query()->create([
                'user_id' => $approver->id,
                'type' => 'leave_application',
                'title' => 'Leave application awaiting approval',
                'body' => sprintf(
                    '%s applied for %s: %s to %s (%s working day(s)).',
                    $leave->user->name,
                    $leave->typeLabel(),
                    $leave->from_date->toDateString(),
                    $leave->to_date->toDateString(),
                    $this->formatDays($leave->working_days),
                ),
                'action_url' => route('leave.show', $leave),
                'data' => ['leave_application_id' => $leave->id],
            ]);
        }
    }

    private function notifyEmployee(LeaveApplication $leave): void
    {
        CrmNotification::query()->create([
            'user_id' => $leave->user_id,
            'type' => 'leave_decision',
            'title' => 'Leave application '.strtolower($leave->status),
            'body' => sprintf(
                'Your %s request for %s to %s was %s by %s.',
                $leave->typeLabel(),
                $leave->from_date->toDateString(),
                $leave->to_date->toDateString(),
                strtolower($leave->status),
                $leave->decider?->name ?? 'management',
            ),
            'action_url' => route('leave.show', $leave),
            'data' => ['leave_application_id' => $leave->id],
        ]);
    }

    public function formatDays(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.');
    }
}
