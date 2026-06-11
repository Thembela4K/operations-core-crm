<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Department;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        $quotations = Quotation::query()
            ->visibleTo($request->user())
            ->with(['latestAssignment.department'])
            ->withCount(['documents', 'submissions'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('quotation_code', 'like', "%{$search}%")
                        ->orWhere('client', 'like', "%{$search}%")
                        ->orWhere('opportunity', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('department_id') && $request->user()->canViewPortfolio(), fn ($query) => $query->whereHas('assignments', fn ($assignment) => $assignment->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('workflow_status'), fn ($query) => $query->whereHas('assignments', fn ($assignment) => $assignment->where('workflow_status', $request->string('workflow_status'))))
            ->when($request->filled('assignment_state'), function ($query) use ($request): void {
                if ($request->string('assignment_state')->toString() === 'assigned') {
                    $query->has('assignments');
                } elseif ($request->string('assignment_state')->toString() === 'unassigned') {
                    $query->doesntHave('assignments');
                }
            })
            ->when($request->filled('deadline_window'), function ($query) use ($request): void {
                match ($request->string('deadline_window')->toString()) {
                    'overdue' => $query->whereDate('valid_until', '<', now()->toDateString()),
                    'today' => $query->whereDate('valid_until', now()->toDateString()),
                    'next_5' => $query->whereBetween('valid_until', [now()->toDateString(), now()->addDays(5)->toDateString()]),
                    'future' => $query->whereDate('valid_until', '>', now()->addDays(5)->toDateString()),
                    default => null,
                };
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('valid_until', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('valid_until', '<=', $request->date('date_to')->toDateString()))
            ->latest('valid_until')
            ->paginate(12)
            ->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'statuses' => Quotation::STATUSES,
            'priorities' => Quotation::PRIORITIES,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'workflowStatuses' => Assignment::WORKFLOW_STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('quotations.create', [
            'quotation' => new Quotation([
                'quotation_code' => $this->nextQuotationCode(),
                'status' => 'Draft',
                'priority' => 'Medium',
                'quoted_amount' => 0,
                'expected_cost' => 0,
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addMonth()->toDateString(),
            ]),
            'statuses' => Quotation::STATUSES,
            'priorities' => Quotation::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateQuotation($request);
        $data += $this->defaultQuotationValues($request);
        $data['created_by'] = $request->user()->id;

        $quotation = Quotation::query()->create($data);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation request created.');
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorizeQuotationAccess($request, $quotation);

        return view('quotations.show', [
            'quotation' => $quotation->load([
                'latestAssignment.department',
                'assignments.department',
                'assignments.submissions',
                'documents.uploader',
                'emailLogs',
                'submissions.department',
                'submissions.submitter',
                'submissions.documents.uploader',
            ]),
            'assignmentForUser' => $this->assignmentForUser($request, $quotation),
        ]);
    }

    public function edit(Request $request, Quotation $quotation): View
    {
        $this->authorizeQuotationEditAccess($request, $quotation);

        return view('quotations.edit', [
            'quotation' => $quotation,
            'statuses' => Quotation::STATUSES,
            'priorities' => Quotation::PRIORITIES,
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotationEditAccess($request, $quotation);

        if (! $request->user()->canManage()) {
            abort(403);
        }

        $data = $this->validateQuotation($request, $quotation);

        $quotation->update($data);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation request updated.');
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        if (! $request->user()->canManage()) {
            abort(403);
        }

        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation request deleted.');
    }

    private function validateQuotation(Request $request, ?Quotation $quotation = null): array
    {
        return $request->validate([
            'quotation_code' => ['required', 'string', 'max:30', Rule::unique('quotations', 'quotation_code')->ignore($quotation)],
            'client' => ['required', 'string', 'max:255'],
            'opportunity' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(Quotation::STATUSES)],
            'priority' => ['required', Rule::in(Quotation::PRIORITIES)],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function defaultQuotationValues(Request $request): array
    {
        return [
            'owner' => $request->user()->name,
            'owner_email' => $request->user()->email,
            'rating' => 0,
            'risk' => 'Medium',
            'win_probability_percent' => 0,
            'quoted_amount' => 0,
            'expected_cost' => 0,
        ];
    }

    private function authorizeQuotationAccess(Request $request, Quotation $quotation): void
    {
        if ($request->user()->canViewPortfolio()) {
            return;
        }

        if (! $quotation->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }

    private function authorizeQuotationEditAccess(Request $request, Quotation $quotation): void
    {
        if ($request->user()->canManage()) {
            return;
        }

        abort(403);
    }

    private function assignmentForUser(Request $request, Quotation $quotation)
    {
        if ($request->user()->canViewPortfolio()) {
            return null;
        }

        $assignment = $quotation->assignments()
            ->where('department_id', $request->user()->department_id)
            ->latest()
            ->first();

        if ($assignment) {
            $assignment->update([
                'read_at' => $assignment->read_at ?? now(),
                'viewed_at' => now(),
                'workflow_status' => $assignment->workflow_status === 'Assigned' ? 'In Progress' : $assignment->workflow_status,
            ]);
        }

        return $assignment;
    }

    private function nextQuotationCode(): string
    {
        $lastCode = Quotation::query()
            ->where('quotation_code', 'like', 'QTN-%')
            ->orderByDesc('id')
            ->value('quotation_code');

        $number = $lastCode ? ((int) substr($lastCode, 4)) + 1 : 1;

        return sprintf('QTN-%03d', $number);
    }
}
