<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, ScoringService $scoring): View
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['latestAssignment.department'])
            ->withCount('documents')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('project_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('owner', 'like', "%{$search}%")
                        ->orWhere('owner_email', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('risk'), fn ($query) => $query->where('risk', $request->string('risk')))
            ->latest('deadline')
            ->paginate(12)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'scoring' => $scoring,
            'statuses' => Project::STATUSES,
            'priorities' => Project::PRIORITIES,
            'risks' => Project::RISKS,
        ]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'project' => new Project([
                'project_code' => $this->nextProjectCode(),
                'status' => 'Not Started',
                'priority' => 'Medium',
                'rating' => 0,
                'risk' => 'Medium',
                'progress_percent' => 0,
                'budget' => 0,
                'start_date' => now()->toDateString(),
                'deadline' => now()->addMonth()->toDateString(),
            ]),
            'statuses' => Project::STATUSES,
            'priorities' => Project::PRIORITIES,
            'risks' => Project::RISKS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProject($request);
        $data['created_by'] = $request->user()->id;

        $project = Project::query()->create($data);

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project, ScoringService $scoring): View
    {
        $this->authorizeProjectAccess($request, $project);

        return view('projects.show', [
            'project' => $project->load(['latestAssignment.department', 'assignments.department', 'documents.uploader', 'emailLogs']),
            'score' => $scoring->projectHealth($project),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        $this->authorizeProjectAccess($request, $project);

        return view('projects.edit', [
            'project' => $project,
            'statuses' => Project::STATUSES,
            'priorities' => Project::PRIORITIES,
            'risks' => Project::RISKS,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $data = $request->user()->canManage()
            ? $this->validateProject($request, $project)
            : $request->validate([
                'status' => ['required', Rule::in(Project::STATUSES)],
                'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
                'notes' => ['nullable', 'string'],
            ]);

        $project->update($data);

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        if (! $request->user()->canManage()) {
            abort(403);
        }

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    private function validateProject(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'project_code' => ['required', 'string', 'max:30', Rule::unique('projects', 'project_code')->ignore($project)],
            'name' => ['required', 'string', 'max:255'],
            'owner' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'priority' => ['required', Rule::in(Project::PRIORITIES)],
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
            'risk' => ['required', Rule::in(Project::RISKS)],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'budget' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function authorizeProjectAccess(Request $request, Project $project): void
    {
        if ($request->user()->canManage()) {
            return;
        }

        if (! $project->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }

    private function nextProjectCode(): string
    {
        $lastCode = Project::query()
            ->where('project_code', 'like', 'PRJ-%')
            ->orderByDesc('id')
            ->value('project_code');

        $number = $lastCode ? ((int) substr($lastCode, 4)) + 1 : 1;

        return sprintf('PRJ-%03d', $number);
    }
}
