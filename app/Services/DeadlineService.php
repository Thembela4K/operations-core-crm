<?php

namespace App\Services;

use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the deadline rows shown on the dashboard and the reminders page.
 *
 * Week-plan entries (tasks) are not Assignment records, so ReminderService
 * never sees them; they are assembled here and merged into the same list.
 *
 * Scope rules:
 *  - management roles: strictly their own deadlines
 *  - reception and department staff: their own plus their department's
 */
class DeadlineService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function taskItems(User $user, ?int $daysAhead = null): Collection
    {
        $daysAhead ??= ReminderService::DASHBOARD_WINDOW_DAYS;

        $horizon = now()->addDays($daysAhead)->startOfDay();
        $today = now()->startOfDay();

        return CrmTask::query()
            ->visibleTo($user)
            ->with(['department', 'assignee'])
            ->whereNotNull('due_date')
            ->get()
            ->reject(fn (CrmTask $task): bool => $task->isClosed())
            ->filter(fn (CrmTask $task): bool => $task->due_date->copy()->startOfDay()->lessThanOrEqualTo($horizon))
            ->filter(fn (CrmTask $task): bool => $this->withinScope($task, $user))
            ->map(function (CrmTask $task) use ($today): array {
                $dueOn = $task->due_date->copy()->startOfDay();

                return [
                    'type' => 'Week Plan Entry',
                    'model' => $task,
                    'reference' => $task->task_number,
                    'title' => $task->title,
                    'department' => $task->department?->name ?? 'Unassigned',
                    'owner' => $task->assignee?->name ?? 'Unassigned',
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_label' => 'Task Due Date',
                    'due_on' => $dueOn,
                    'days_left' => (int) $today->diffInDays($dueOn, false),
                    'portal_url' => route('tasks.show', $task),
                ];
            })
            ->sortBy('days_left')
            ->values();
    }

    /**
     * Narrows assignment-based deadline rows to what the viewer may see.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function filterAssignments(Collection $items, User $user): Collection
    {
        if ($user->deadlinesArePersonalOnly()) {
            return $items
                ->filter(fn (array $item): bool => (int) ($item['assignment']->assigned_user_id ?? 0) === $user->id)
                ->values();
        }

        if ($user->canSeeCompanyDeadlines()) {
            return $items->values();
        }

        return $items
            ->filter(fn (array $item): bool => ($item['assignment']->department_id ?? null) === $user->department_id)
            ->values();
    }

    private function withinScope(CrmTask $task, User $user): bool
    {
        if ($user->deadlinesArePersonalOnly()) {
            return $task->assigned_to === $user->id || $task->created_by === $user->id;
        }

        if ($user->canSeeCompanyDeadlines()) {
            return true;
        }

        return $task->department_id === $user->department_id || $task->assigned_to === $user->id;
    }
}
