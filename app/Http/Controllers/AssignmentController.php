<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\EmailLog;
use App\Models\Quotation;
use App\Models\StaffMember;
use App\Models\TenderProposal;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\AuditLogService;
use App\Services\AssignmentEmailService;
use App\Services\CrmNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Assignment Center.
 *
 * Work is assigned to a department and a person (or to yourself), as a task or
 * as a meeting with a date and time. The assignee works it from their own queue;
 * everything lands in the Week Plan and every step is kept in a trail.
 */
class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $request->string('scope', 'mine')->toString();
        $canSeeAll = $this->canSeeAll($user);

        if (! $canSeeAll) {
            $scope = 'mine';
        }

        $query = Assignment::query()
            ->with(['assignable', 'department', 'assignedUser'])
            ->when($scope === 'mine', fn ($q) => $q->forUser($user))
            ->when($request->filled('workflow_status'), fn ($q) => $q->where('workflow_status', $request->string('workflow_status')))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('assignee_name', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN workflow_status IN ('Completed','Cancelled') THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->latest('id');

        return view('assignments.index', [
            'assignments' => $query->paginate(15)->withQueryString(),
            'scope' => $scope,
            'canSeeAll' => $canSeeAll,
            'overdueCount' => Assignment::query()
                ->when(! $canSeeAll, fn ($q) => $q->forUser($user))
                ->overdue()
                ->count(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'workflowStatuses' => Assignment::WORKFLOW_STATUSES,
            'kinds' => Assignment::KINDS,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        if (! $this->canAssign($user)) {
            abort(403);
        }

        return view('assignments.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'staffMembers' => StaffMember::query()->where('is_active', true)->with('department')->orderBy('name')->get(),
            'tenderProposals' => TenderProposal::query()->orderBy('title')->get(),
            'quotations' => Quotation::query()->orderBy('opportunity')->get(),
            'kinds' => Assignment::KINDS,
        ]);
    }

    public function store(Request $request, AssignmentService $service, AuditLogService $audit): RedirectResponse
    {
        if (! $this->canAssign($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::in(Assignment::KINDS)],
            'title' => ['required', 'string', 'max:255'],
            'target' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'assign_to_me' => ['nullable', 'boolean'],
            'assignee_name' => ['nullable', 'string', 'max:255'],
            'assignee_email' => ['nullable', 'email', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        $assignee = $this->resolveAssignee($data, $request->user());
        [$assignableType, $assignableId] = $this->resolveTarget($data['target'] ?? null);

        $assignment = $service->create([
            'assignable_type' => $assignableType,
            'assignable_id' => $assignableId,
            'title' => $data['title'],
            'kind' => $data['kind'],
            'department_id' => $assignee['department_id'],
            'assigned_user_id' => $assignee['user_id'],
            'assignee_name' => $assignee['name'],
            'assignee_email' => $assignee['email'],
            'due_date' => $data['due_date'] ?? ($data['starts_at'] ?? null),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'instructions' => $data['instructions'] ?? null,
        ], $request->user());

        $audit->record('assignment_created', $assignment, "Assignment created: {$assignment->label()}.");

        if ($request->boolean('send_email') && $assignment->assignee_email) {
            $emailService = app(AssignmentEmailService::class);
            $status = $emailService->send($assignment);
            $assignment->update(['status' => $status]);

            return redirect()->route('assignments.show', $assignment)->with(
                $status === 'Assignment Email Sent' ? 'success' : 'warning',
                $status === 'Assignment Email Sent'
                    ? 'Assignment saved and emailed.'
                    : 'Assignment saved, but the email failed. Check the email log.',
            );
        }

        return redirect()->route('assignments.show', $assignment)->with('success', 'Assignment saved.');
    }

    public function show(Request $request, Assignment $assignment): View
    {
        $this->authorizeView($request, $assignment);

        return view('assignments.show', [
            'assignment' => $assignment->load(['assignable', 'department', 'assignedUser', 'events.actor']),
            'canAct' => $this->canAct($request->user(), $assignment),
            'canAssign' => $this->canAssign($request->user()),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'staffMembers' => StaffMember::query()->where('is_active', true)->with('department')->orderBy('name')->get(),
        ]);
    }

    public function transition(Request $request, Assignment $assignment, AssignmentService $service, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeView($request, $assignment);

        if (! $this->canAct($request->user(), $assignment)) {
            abort(403);
        }

        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(AssignmentService::TRANSITIONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = $assignment->workflow_status;
        $service->transition($assignment, $data['action'], $request->user(), $data['notes'] ?? null);

        $audit->record('assignment_'.$data['action'], $assignment, "Assignment {$from} → {$assignment->workflow_status}.");

        return back()->with('success', "Assignment moved to {$assignment->workflow_status}.");
    }

    public function reassign(Request $request, Assignment $assignment, AssignmentService $service, AuditLogService $audit): RedirectResponse
    {
        if (! $this->canAssign($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'assign_to_me' => ['nullable', 'boolean'],
            'assignee_name' => ['nullable', 'string', 'max:255'],
            'assignee_email' => ['nullable', 'email', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignee = $this->resolveAssignee($data, $request->user(), $assignment);

        $service->reassign($assignment, [
            'department_id' => $assignee['department_id'],
            'assigned_user_id' => $assignee['user_id'],
            'assignee_name' => $assignee['name'],
            'assignee_email' => $assignee['email'],
            'due_date' => $data['due_date'] ?? $assignment->due_date?->toDateString(),
        ], $request->user(), $data['notes'] ?? null);

        $audit->record('assignment_reassigned', $assignment, "Assignment reassigned to {$assignee['name']}.");

        return back()->with('success', 'Assignment reassigned.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{department_id: int, user_id: ?int, name: string, email: ?string}
     */
    private function resolveAssignee(array $data, User $actor, ?Assignment $existing = null): array
    {
        // Assigning to yourself is a first-class choice.
        if (($data['assigned_user_id'] ?? null) === null && ! empty($data['assign_to_me'])) {
            return [
                'department_id' => $actor->department_id ?? $existing?->department_id,
                'user_id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ];
        }

        $user = isset($data['assigned_user_id'])
            ? User::query()->where('is_active', true)->find($data['assigned_user_id'])
            : null;

        $departmentId = $data['department_id'] ?? $user?->department_id ?? $existing?->department_id;

        if (! $departmentId) {
            abort(422, 'Choose a department or a person.');
        }

        $department = Department::query()->findOrFail($departmentId);

        return [
            'department_id' => (int) $department->id,
            'user_id' => $user?->id,
            'name' => $user?->name ?: ($data['assignee_name'] ?? '') ?: $department->name,
            'email' => $user?->email ?: ($data['assignee_email'] ?? null) ?: $department->email,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveTarget(?string $target): array
    {
        if (! $target) {
            return [null, null];
        }

        [$module, $id] = array_pad(explode(':', $target, 2), 2, null);

        if (! is_numeric($id)) {
            return [null, null];
        }

        return match ($module) {
            'tender_proposal' => [TenderProposal::class, (int) $id],
            'quotation' => [Quotation::class, (int) $id],
            default => [null, null],
        };
    }

    private function canSeeAll(User $user): bool
    {
        return $user->hasRole(User::ROLE_BUSINESS_ADMIN, User::ROLE_DIRECTOR, User::ROLE_RECEPTION)
            || $user->isDepartmentHead();
    }

    private function canAssign(User $user): bool
    {
        return true;
    }

    private function canAct(User $user, Assignment $assignment): bool
    {
        return $assignment->assigned_user_id === $user->id
            || $user->hasRole(User::ROLE_BUSINESS_ADMIN, User::ROLE_DIRECTOR);
    }

    private function authorizeView(Request $request, Assignment $assignment): void
    {
        $user = $request->user();

        if ($assignment->assigned_user_id === $user->id
            || $assignment->created_by === $user->id
            || $this->canSeeAll($user)) {
            return;
        }

        abort(403);
    }
}
