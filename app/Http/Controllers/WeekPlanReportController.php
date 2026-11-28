<?php

namespace App\Http\Controllers;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use App\Models\WeekPlanReport;
use App\Models\WeekPlanReportTask;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Weekly Report & Plan.
 *
 * The employee records how far last week's tasks got, what they are doing this
 * week (with unfinished work carried over), and who else contributed. Reviewers
 * read the same report.
 */
class WeekPlanReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = WeekPlanReport::query()
            ->visibleTo($request->user())
            ->with(['user', 'department'])
            ->withCount(['tasks', 'contributors'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->whereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            })
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderByDesc('week_start')
            ->paginate(15)
            ->withQueryString();

        return view('week-reports.index', [
            'reports' => $reports,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'canViewAll' => $request->user()->canViewReports(),
        ]);
    }

    public function edit(Request $request): View
    {
        $user = $request->user();
        $anchor = $request->filled('week') ? $request->date('week') : null;
        $anchor ??= now();
        $start = $anchor->copy()->startOfWeek();
        $end = $start->copy()->copy()->endOfWeek();

        $report = WeekPlanReport::query()
            ->where('user_id', $user->id)
            ->whereDate('week_start', $start->toDateString())
            ->with(['tasks', 'contributors.user'])
            ->first();

        if (! $report) {
            $report = new WeekPlanReport([
                'user_id' => $user->id,
                'department_id' => $user->department_id,
                'week_start' => $start->toDateString(),
                'week_end' => $end->toDateString(),
                'status' => WeekPlanReport::STATUS_DRAFT,
            ]);
        }

        $lastWeekStart = $start->copy()->subWeek();
        $lastWeekEnd = $start->copy()->subDay()->endOfDay();

        return view('week-reports.form', [
            'report' => $report,
            'start' => $start,
            'end' => $end,
            'lastWeekStart' => $lastWeekStart,
            'lastWeekEnd' => $lastWeekEnd,
            'lastWeekRows' => $this->lastWeekRows($report, $user, $lastWeekStart, $lastWeekEnd),
            'thisWeekRows' => $this->thisWeekRows($report, $user, $start, $end),
            'lastWeekOptions' => $this->taskOptions($user, $lastWeekStart, $lastWeekEnd),
            'thisWeekOptions' => $this->taskOptions($user, $start, $end),
            'staff' => User::query()->where('is_active', true)->whereKeyNot($user->id)->orderBy('name')->get(),
            'previousWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
        ]);
    }

    /**
     * Tasks the employee can pick from when building a section of the report.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function taskOptions(User $user, Carbon $start, Carbon $end): Collection
    {
        return $this->tasksBetween($user, $start, $end)
            ->map(fn (CrmTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'progress' => $this->defaultProgress($task),
            ])
            ->values();
    }

    public function save(Request $request, AuditLogService $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'last_week_progress' => ['nullable', 'string', 'max:5000'],
            'this_week_plan' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:Draft,Submitted'],
            'last_week' => ['nullable', 'array'],
            'last_week.*.title' => ['nullable', 'string', 'max:255'],
            'last_week.*.progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'last_week.*.notes' => ['nullable', 'string', 'max:1000'],
            'last_week.*.task_id' => ['nullable', 'integer'],
            'this_week' => ['nullable', 'array'],
            'this_week.*.title' => ['nullable', 'string', 'max:255'],
            'this_week.*.progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'this_week.*.planned_start' => ['nullable', 'date'],
            'this_week.*.planned_end' => ['nullable', 'date', 'after_or_equal:this_week.*.planned_start'],
            'this_week.*.notes' => ['nullable', 'string', 'max:1000'],
            'this_week.*.carried_over' => ['nullable', 'boolean'],
            'contributors' => ['nullable', 'array'],
            'contributors.*.user_id' => ['nullable', 'exists:users,id'],
            'contributors.*.name' => ['nullable', 'string', 'max:120'],
            'contributors.*.organisation' => ['nullable', 'string', 'max:120'],
            'contributors.*.contribution' => ['nullable', 'string', 'max:255'],
        ]);

        $start = Carbon::parse($data['week_start'])->startOfWeek();

        $report = WeekPlanReport::query()->firstOrNew([
            'user_id' => $user->id,
            'week_start' => $start->toDateString(),
        ]);

        $report->fill([
            'department_id' => $report->department_id ?: $user->department_id,
            'week_end' => $start->copy()->endOfWeek()->toDateString(),
            'last_week_progress' => $data['last_week_progress'] ?? null,
            'this_week_plan' => $data['this_week_plan'] ?? null,
            'status' => $data['status'],
            'submitted_at' => $data['status'] === WeekPlanReport::STATUS_SUBMITTED ? now() : null,
        ])->save();

        $this->syncTasks($report, WeekPlanReport::SECTION_LAST_WEEK, $data['last_week'] ?? []);
        $this->syncTasks($report, WeekPlanReport::SECTION_THIS_WEEK, $data['this_week'] ?? []);
        $this->syncContributors($report, $data['contributors'] ?? []);

        $audit->record('week_report_saved', $report, "Weekly report for week of {$report->week_start->toDateString()} saved ({$report->status}).");

        return redirect()
            ->route('week-reports.show', $report)
            ->with('success', $report->status === WeekPlanReport::STATUS_SUBMITTED
                ? 'Weekly report submitted.'
                : 'Weekly report saved as a draft.');
    }

    public function show(Request $request, WeekPlanReport $weekPlanReport): View
    {
        $this->authorizeView($request, $weekPlanReport);

        return view('week-reports.show', [
            'report' => $weekPlanReport->load(['user', 'department', 'tasks', 'contributors.user']),
            'canEdit' => $weekPlanReport->user_id === $request->user()->id,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function lastWeekRows(WeekPlanReport $report, User $user, Carbon $start, Carbon $end): Collection
    {
        $saved = $report->exists
            ? $report->tasks->where('section', WeekPlanReport::SECTION_LAST_WEEK)
            : collect();

        if ($saved->isNotEmpty()) {
            return $saved->map(fn (WeekPlanReportTask $row): array => [
                'task_id' => $row->task_id,
                'title' => $row->title,
                'progress_percent' => $row->progress_percent,
                'notes' => $row->notes,
            ])->values();
        }

        return $this->tasksBetween($user, $start, $end)
            ->map(fn (CrmTask $task): array => [
                'task_id' => $task->id,
                'title' => $task->title,
                'progress_percent' => $this->defaultProgress($task),
                'notes' => null,
            ])->values();
    }

    /**
     * This week starts with whatever was not finished last week, then anything
     * already due this week.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function thisWeekRows(WeekPlanReport $report, User $user, Carbon $start, Carbon $end): Collection
    {
        $saved = $report->exists
            ? $report->tasks->where('section', WeekPlanReport::SECTION_THIS_WEEK)
            : collect();

        if ($saved->isNotEmpty()) {
            return $saved->map(fn (WeekPlanReportTask $row): array => [
                'task_id' => $row->task_id,
                'title' => $row->title,
                'progress_percent' => $row->progress_percent,
                'planned_start' => $row->planned_start?->toDateString(),
                'planned_end' => $row->planned_end?->toDateString(),
                'carried_over' => $row->carried_over,
                'notes' => $row->notes,
            ])->values();
        }

        $carried = $this->tasksBetween($user, $start->copy()->subWeek(), $start->copy()->subDay()->endOfDay())
            ->reject(fn (CrmTask $task): bool => $this->defaultProgress($task) >= 100)
            ->map(fn (CrmTask $task): array => [
                'task_id' => $task->id,
                'title' => $task->title,
                'progress_percent' => $this->defaultProgress($task),
                'planned_start' => $task->due_date?->toDateString(),
                'planned_end' => $task->due_date?->toDateString(),
                'carried_over' => true,
                'notes' => null,
            ]);

        $planned = $this->tasksBetween($user, $start, $end)
            ->reject(fn (CrmTask $task): bool => $carried->contains(fn (array $row): bool => $row['task_id'] === $task->id))
            ->map(fn (CrmTask $task): array => [
                'task_id' => $task->id,
                'title' => $task->title,
                'progress_percent' => 0,
                'planned_start' => $task->due_date?->toDateString(),
                'planned_end' => $task->due_date?->toDateString(),
                'carried_over' => false,
                'notes' => null,
            ]);

        return $carried->concat($planned)->values();
    }

    private function tasksBetween(User $user, Carbon $start, Carbon $end): Collection
    {
        return CrmTask::query()
            ->where(function ($query) use ($user): void {
                $query->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
            })
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('created_at', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end): void {
                        $inner->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end]);
                    });
            })
            ->orderBy('due_date')
            ->get();
    }

    private function defaultProgress(CrmTask $task): int
    {
        return match ($task->status) {
            CrmTask::STATUS_DONE => 100,
            CrmTask::STATUS_IN_PROGRESS => 50,
            default => 0,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTasks(WeekPlanReport $report, string $section, array $rows): void
    {
        $report->tasks()->where('section', $section)->delete();

        $position = 1;

        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $report->tasks()->create([
                'task_id' => $row['task_id'] ?? null,
                'title' => $title,
                'section' => $section,
                'progress_percent' => (int) ($row['progress_percent'] ?? 0),
                'planned_start' => $row['planned_start'] ?? null,
                'planned_end' => $row['planned_end'] ?? null,
                'carried_over' => (bool) ($row['carried_over'] ?? false),
                'notes' => $row['notes'] ?? null,
                'position' => $position++,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncContributors(WeekPlanReport $report, array $rows): void
    {
        $report->contributors()->delete();

        foreach ($rows as $row) {
            $userId = $row['user_id'] ?? null;
            $name = trim((string) ($row['name'] ?? ''));

            if (! $userId && $name === '') {
                continue;
            }

            $report->contributors()->create([
                'user_id' => $userId ?: null,
                'name' => $userId ? null : $name,
                'organisation' => $row['organisation'] ?? null,
                'contribution' => $row['contribution'] ?? null,
            ]);
        }
    }

    private function authorizeView(Request $request, WeekPlanReport $report): void
    {
        $user = $request->user();

        if ($report->user_id === $user->id || $user->canViewReports()) {
            return;
        }

        if ($user->isDepartmentHead() && $report->department_id === $user->department_id) {
            return;
        }

        abort(403);
    }
}
