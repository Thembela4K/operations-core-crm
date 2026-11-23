<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Project::query()
            ->visibleTo($user)
            ->with(['user', 'department'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when(
                $request->filled('user_id') && $user->canViewAllProjects(),
                fn ($q) => $q->where('user_id', $request->integer('user_id'))
            )
            ->when(
                $request->filled('department_id') && $user->canViewAllProjects(),
                fn ($q) => $q->where('department_id', $request->integer('department_id'))
            )
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner
                    ->where('title', 'like', $term)
                    ->orWhere('comment', 'like', $term));
            });

        $metrics = (clone $query)->get();

        $projects = $query
            ->orderBy('user_id')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'grouped' => $projects->getCollection()->groupBy('user_id'),
            'metrics' => [
                'total' => $metrics->count(),
                'open' => $metrics->filter(fn (Project $project): bool => ! $project->isClosed())->count(),
                'completed' => $metrics->where('status', Project::STATUS_COMPLETED)->count(),
                'average' => $metrics->isNotEmpty() ? round($metrics->avg('progress_percent')) : 0,
            ],
            'statuses' => Project::STATUSES,
            'users' => $user->canViewAllProjects()
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'canViewAll' => $user->canViewAllProjects(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('projects.form', [
            'project' => new Project([
                'status' => Project::STATUS_NOT_STARTED,
                'progress_percent' => 0,
                'planned_date' => now()->toDateString(),
                'by_whom' => $user->name,
                'time_frame' => '1 Day',
            ]),
            'statuses' => Project::STATUSES,
            'users' => $user->canAdministerProjects()
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $owner = $this->resolveOwner($request, $data);

        $attributes = $this->attributes($data, $owner);
        $attributes['created_by'] = $request->user()->id;
        $attributes['completed_at'] = $this->completedStamp($attributes['status'], null);

        $project = Project::query()->create($attributes);
        $audit->record('project_created', $project, "Project created for {$owner->name}: {$project->title}.");

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project added.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorizeView($request, $project);

        $project->load(['user', 'department', 'creator']);

        return view('projects.show', [
            'project' => $project,
            'canEdit' => $this->canEdit($request->user(), $project),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        $this->authorizeEdit($request, $project);

        return view('projects.form', [
            'project' => $project,
            'statuses' => Project::STATUSES,
            'users' => $request->user()->canAdministerProjects()
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function update(Request $request, Project $project, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeEdit($request, $project);

        $data = $this->validated($request);
        $owner = $this->resolveOwner($request, $data);

        $attributes = $this->attributes($data, $owner);
        $attributes['completed_at'] = $this->completedStamp($attributes['status'], $project->completed_at);

        $project->update($attributes);

        $audit->record('project_updated', $project, "Project updated: {$project->title}.");

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeEdit($request, $project);

        $audit->record('project_deleted', $project, "Project deleted: {$project->title}.");
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'planned_date' => ['nullable', 'date'],
            'by_whom' => ['nullable', 'string', 'max:255'],
            'time_frame' => ['nullable', 'string', 'max:60'],
            'started_on' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:started_on'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOwner(Request $request, array $data): User
    {
        $actor = $request->user();

        if (! empty($data['user_id']) && (int) $data['user_id'] !== $actor->id) {
            if (! $actor->canAdministerProjects()) {
                abort(403);
            }

            return User::query()->findOrFail((int) $data['user_id']);
        }

        return $actor;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, User $owner): array
    {
        $status = (string) $data['status'];
        $progress = (int) $data['progress_percent'];

        // Completing a project means 100%.
        if ($status === Project::STATUS_COMPLETED) {
            $progress = 100;
        }

        return [
            'user_id' => $owner->id,
            'department_id' => $owner->department_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $status,
            'progress_percent' => $progress,
            'comment' => $data['comment'] ?? null,
            'planned_date' => $data['planned_date'] ?? null,
            'by_whom' => $data['by_whom'] ?? null,
            'time_frame' => $data['time_frame'] ?? null,
            'started_on' => $data['started_on'] ?? null,
            'target_date' => $data['target_date'] ?? null,
        ];
    }

    /**
     * Keeps the original completion time when a closed project is re-saved,
     * and clears it when the project is reopened.
     */
    private function completedStamp(string $status, mixed $existing): mixed
    {
        if (! in_array($status, Project::CLOSED_STATUSES, true)) {
            return null;
        }

        return $existing ?? now();
    }

    private function authorizeView(Request $request, Project $project): void
    {
        $user = $request->user();

        abort_unless($user->canViewAllProjects() || $project->user_id === $user->id, 403);
    }

    private function authorizeEdit(Request $request, Project $project): void
    {
        abort_unless($this->canEdit($request->user(), $project), 403);
    }

    private function canEdit(User $user, Project $project): bool
    {
        return $project->user_id === $user->id || $user->canAdministerProjects();
    }
}
