<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CrmTask;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\TenderProposal;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CrmNotificationService;
use App\Services\FinanceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = CrmTask::query()
            ->visibleTo($request->user())
            ->with(['department', 'assignee', 'creator', 'taskable'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('task_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('department_id') && $request->user()->canViewReports(), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderByRaw("CASE WHEN status IN ('Done','Cancelled') THEN 1 ELSE 0 END")
            ->orderByRaw('due_date is null, due_date asc')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'statuses' => CrmTask::STATUSES,
            'priorities' => CrmTask::PRIORITIES,
            'users' => $this->usersFor($request),
            'departments' => $this->departmentsFor($request),
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        return view('tasks.create', [
            'task' => new CrmTask([
                'task_number' => $numbers->taskNumber(),
                'status' => CrmTask::STATUS_TO_DO,
                'priority' => 'Medium',
                'department_id' => $request->user()->department_id,
                'assigned_to' => $request->user()->id,
            ]),
            ...$this->formData($request),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['task_number'] = $data['task_number'] ?: $numbers->taskNumber();
        $data['created_by'] = $request->user()->id;
        $data = $this->applyTaskable($data);

        if (! $request->user()->canViewReports()) {
            $data['department_id'] = $request->user()->department_id;
        }

        $task = CrmTask::query()->create($data);
        $audit->record('created', $task, "Task {$task->task_number} created.");
        $this->notifyAssignee($task, $notifications);

        return redirect()->route('tasks.show', $task)->with('success', 'Task created.');
    }

    public function show(Request $request, CrmTask $task): View
    {
        $this->authorizeView($request, $task);

        return view('tasks.show', [
            'task' => $task->load(['department', 'assignee', 'creator', 'comments.user', 'documents.uploader', 'auditLogs.user', 'taskable']),
            'statuses' => CrmTask::STATUSES,
        ]);
    }

    public function edit(Request $request, CrmTask $task): View
    {
        $this->authorizeMutate($request, $task);

        return view('tasks.edit', [
            'task' => $task,
            ...$this->formData($request),
        ]);
    }

    public function update(Request $request, CrmTask $task, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutate($request, $task);

        $before = $task->only(['title', 'status', 'priority', 'assigned_to', 'due_date']);
        $data = $this->applyTaskable($this->validated($request, $task));

        if (! $request->user()->canViewReports()) {
            $data['department_id'] = $request->user()->department_id;
        }

        $this->applyStatusTimestamps($task, $data);
        $task->update($data);
        $audit->record('updated', $task, "Task {$task->task_number} updated.", $before, $task->only(['title', 'status', 'priority', 'assigned_to', 'due_date']));
        $this->notifyAssignee($task, $notifications);

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated.');
    }

    public function destroy(Request $request, CrmTask $task, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutate($request, $task);
        $audit->record('deleted', $task, "Task {$task->task_number} deleted.");
        $task->delete();

        return redirect()->route('tasks.index')->with('success', 'Task deleted.');
    }

    public function comment(Request $request, CrmTask $task, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeView($request, $task);
        $data = $request->validate(['body' => ['required', 'string']]);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $audit->record('commented', $task, "Comment added to task {$task->task_number}.");

        if ($task->assigned_to && $task->assigned_to !== $request->user()->id) {
            $notifications->notifyUser($task->assignee, 'task_comment', "New comment on {$task->task_number}", $task->title, route('tasks.show', $task));
        }

        return back()->with('success', 'Comment added.');
    }

    public function status(Request $request, CrmTask $task, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeMutate($request, $task);
        $data = $request->validate(['status' => ['required', Rule::in(CrmTask::STATUSES)]]);
        $before = ['status' => $task->status];
        $this->applyStatusTimestamps($task, $data);
        $task->update($data);
        $audit->record('status_changed', $task, "Task {$task->task_number} status changed to {$task->status}.", $before, ['status' => $task->status]);

        return back()->with('success', 'Task status updated.');
    }

    private function validated(Request $request, ?CrmTask $task = null): array
    {
        return $request->validate([
            'task_number' => ['nullable', 'string', 'max:40', Rule::unique('crm_tasks', 'task_number')->ignore($task)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(CrmTask::STATUSES)],
            'priority' => ['required', Rule::in(CrmTask::PRIORITIES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'related_record' => ['nullable', 'string'],
        ]);
    }

    private function applyTaskable(array $data): array
    {
        $related = $data['related_record'] ?? null;
        unset($data['related_record']);

        if (! $related || ! str_contains($related, ':')) {
            $data['taskable_type'] = null;
            $data['taskable_id'] = null;

            return $data;
        }

        [$type, $id] = explode(':', $related, 2);
        $map = $this->taskableMap();

        if (! isset($map[$type])) {
            return $data;
        }

        $data['taskable_type'] = $map[$type];
        $data['taskable_id'] = (int) $id;

        return $data;
    }

    private function applyStatusTimestamps(CrmTask $task, array &$data): void
    {
        if (($data['status'] ?? null) === CrmTask::STATUS_IN_PROGRESS && ! $task->started_at) {
            $data['started_at'] = now();
        }

        if (($data['status'] ?? null) === CrmTask::STATUS_DONE && ! $task->completed_at) {
            $data['completed_at'] = now();
        }
    }

    private function notifyAssignee(CrmTask $task, CrmNotificationService $notifications): void
    {
        if (! $task->assigned_to || ! $task->assignee) {
            return;
        }

        $notifications->notifyUser($task->assignee, 'task_assignment', "Task assigned: {$task->task_number}", $task->title, route('tasks.show', $task));
    }

    private function authorizeView(Request $request, CrmTask $task): void
    {
        if ($request->user()->canViewReports() || $request->user()->canManage() || $task->department_id === $request->user()->department_id || $task->assigned_to === $request->user()->id || $task->created_by === $request->user()->id) {
            return;
        }

        abort(403);
    }

    private function authorizeMutate(Request $request, CrmTask $task): void
    {
        if ($request->user()->canManage() || $task->assigned_to === $request->user()->id || $task->created_by === $request->user()->id) {
            return;
        }

        abort(403);
    }

    private function formData(Request $request): array
    {
        return [
            'statuses' => CrmTask::STATUSES,
            'priorities' => CrmTask::PRIORITIES,
            'departments' => $this->departmentsFor($request),
            'users' => $this->usersFor($request),
            'relatedRecords' => $this->relatedRecords($request),
        ];
    }

    private function departmentsFor(Request $request)
    {
        if (! $request->user()->canViewReports()) {
            return Department::query()->whereKey($request->user()->department_id)->get();
        }

        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function usersFor(Request $request)
    {
        return User::query()
            ->where('is_active', true)
            ->when(! $request->user()->canViewReports(), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->orderBy('name')
            ->get();
    }

    private function relatedRecords(Request $request): array
    {
        if (! $request->user()->canViewReports() && ! $request->user()->canManage()) {
            return [];
        }

        return [
            'Clients' => Client::query()->orderBy('name')->limit(80)->get()->map(fn (Client $record): array => ['value' => 'client:'.$record->id, 'label' => $record->client_code.' - '.$record->name])->all(),
            'Tender Proposals' => TenderProposal::query()->latest()->limit(80)->get()->map(fn (TenderProposal $record): array => ['value' => 'tender:'.$record->id, 'label' => $record->tender_reference.' - '.$record->title])->all(),
            'Quotation Requests' => Quotation::query()->latest()->limit(80)->get()->map(fn (Quotation $record): array => ['value' => 'quotation:'.$record->id, 'label' => $record->quotation_code.' - '.$record->opportunity])->all(),
            'Sales Quotations' => SalesQuotation::query()->latest()->limit(80)->get()->map(fn (SalesQuotation $record): array => ['value' => 'sales_quotation:'.$record->id, 'label' => $record->quotation_number.' - '.$record->title])->all(),
            'Invoices' => Invoice::query()->latest()->limit(80)->get()->map(fn (Invoice $record): array => ['value' => 'invoice:'.$record->id, 'label' => $record->invoice_number])->all(),
            'Requisitions' => Requisition::query()->latest()->limit(80)->get()->map(fn (Requisition $record): array => ['value' => 'requisition:'.$record->id, 'label' => $record->requisition_number.' - '.$record->title])->all(),
            'Expenses' => Expense::query()->latest()->limit(80)->get()->map(fn (Expense $record): array => ['value' => 'expense:'.$record->id, 'label' => $record->expense_number.' - '.$record->payee])->all(),
        ];
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function taskableMap(): array
    {
        return [
            'client' => Client::class,
            'tender' => TenderProposal::class,
            'quotation' => Quotation::class,
            'sales_quotation' => SalesQuotation::class,
            'invoice' => Invoice::class,
            'requisition' => Requisition::class,
            'expense' => Expense::class,
        ];
    }
}
