<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\TenderProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenderProposalController extends Controller
{
    public const DATE_TYPES = [
        'Site Visit',
        'Clarification Window',
    ];

    public function index(Request $request): View
    {
        $tenderProposals = TenderProposal::query()
            ->visibleTo($request->user())
            ->with(['latestAssignment.department'])
            ->withCount(['documents', 'submissions'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('tender_reference', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('brief', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('closing_date')
            ->paginate(12)
            ->withQueryString();

        return view('tender_proposals.index', [
            'tenderProposals' => $tenderProposals,
            'statuses' => TenderProposal::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('tender_proposals.create', [
            'tenderProposal' => new TenderProposal([
                'tender_reference' => $this->nextTenderReference(),
                'status' => 'Draft',
                'received_date' => now()->toDateString(),
                'closing_date' => now()->addMonth()->toDateString(),
            ]),
            'statuses' => TenderProposal::STATUSES,
            'dateTypes' => self::DATE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTenderProposal($request);
        $data += $this->defaultTenderProposalValues($request);
        $data['created_by'] = $request->user()->id;

        $tenderProposal = TenderProposal::query()->create($data);
        $this->syncImportantDates($request, $tenderProposal);
        $this->storeTenderDocument($request, $tenderProposal);

        return redirect()->route('assignments.index', [
            'target' => "tender_proposal:{$tenderProposal->id}",
        ])->with('success', 'Tender proposal created. Assign it to the responsible department.');
    }

    public function show(Request $request, TenderProposal $tenderProposal): View
    {
        $this->authorizeTenderProposalAccess($request, $tenderProposal);
        $assignmentForUser = $this->assignmentForUser($request, $tenderProposal);
        $this->markAssignmentViewed($assignmentForUser);

        return view('tender_proposals.show', [
            'tenderProposal' => $tenderProposal->load([
                'latestAssignment.department',
                'assignments.department',
                'assignments.submissions',
                'importantDates',
                'documents.uploader',
                'emailLogs',
                'submissions.department',
                'submissions.submitter',
                'submissions.documents.uploader',
            ]),
            'assignmentForUser' => $assignmentForUser,
        ]);
    }

    public function edit(Request $request, TenderProposal $tenderProposal): View
    {
        $this->authorizeTenderProposalEditAccess($request, $tenderProposal);

        return view('tender_proposals.edit', [
            'tenderProposal' => $tenderProposal->load('importantDates'),
            'statuses' => TenderProposal::STATUSES,
            'dateTypes' => self::DATE_TYPES,
        ]);
    }

    public function update(Request $request, TenderProposal $tenderProposal): RedirectResponse
    {
        $this->authorizeTenderProposalEditAccess($request, $tenderProposal);

        if (! $request->user()->canManage()) {
            abort(403);
        }

        $data = $this->validateTenderProposal($request, $tenderProposal);

        $tenderProposal->update($data);
        $this->syncImportantDates($request, $tenderProposal);
        $this->storeTenderDocument($request, $tenderProposal);

        return redirect()->route('tender-proposals.show', $tenderProposal)
            ->with('success', 'Tender proposal updated.');
    }

    public function destroy(Request $request, TenderProposal $tenderProposal): RedirectResponse
    {
        if (! $request->user()->canManage()) {
            abort(403);
        }

        $tenderProposal->delete();

        return redirect()->route('tender-proposals.index')->with('success', 'Tender proposal deleted.');
    }

    private function validateTenderProposal(Request $request, ?TenderProposal $tenderProposal = null): array
    {
        $request->validate([
            'important_dates' => ['nullable', 'array'],
            'important_dates.*.label' => ['nullable', Rule::in(self::DATE_TYPES)],
            'important_dates.*.due_date' => ['nullable', 'date'],
            'important_dates.*.notes' => ['nullable', 'string'],
            'tender_document' => [$tenderProposal ? 'nullable' : 'required', 'file', 'max:20480'],
        ]);

        return $request->validate([
            'tender_reference' => ['required', 'string', 'max:40', Rule::unique('tender_proposals', 'tender_reference')->ignore($tenderProposal)],
            'title' => ['required', 'string', 'max:255'],
            'closing_date' => ['required', 'date'],
            'brief' => ['nullable', 'string'],
        ]);
    }

    private function syncImportantDates(Request $request, TenderProposal $tenderProposal): void
    {
        $tenderProposal->importantDates()->delete();
        $rowsByLabel = collect($request->input('important_dates', []))
            ->filter(fn (array $row): bool => filled($row['label'] ?? null))
            ->keyBy('label');

        foreach (self::DATE_TYPES as $dateType) {
            $row = $rowsByLabel->get($dateType);

            if (! $row || blank($row['due_date'] ?? null)) {
                continue;
            }

            $tenderProposal->importantDates()->create([
                'label' => $dateType,
                'due_date' => $row['due_date'],
                'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
            ]);
        }
    }

    private function storeTenderDocument(Request $request, TenderProposal $tenderProposal): void
    {
        if (! $request->hasFile('tender_document')) {
            return;
        }

        $file = $request->file('tender_document');
        $path = $file->store("documents/tender_proposal/{$tenderProposal->id}");

        $tenderProposal->documents()->create([
            'category' => Document::CATEGORY_ORIGINAL_TENDER,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    private function defaultTenderProposalValues(Request $request): array
    {
        return [
            'owner' => $request->user()->name,
            'owner_email' => $request->user()->email,
            'status' => 'Draft',
            'priority' => 'Medium',
            'rating' => 0,
            'risk' => 'Medium',
            'progress_percent' => 0,
            'budget' => 0,
            'received_date' => now()->toDateString(),
        ];
    }

    private function authorizeTenderProposalAccess(Request $request, TenderProposal $tenderProposal): void
    {
        if ($request->user()->canViewPortfolio()) {
            return;
        }

        if (! $tenderProposal->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }

    private function authorizeTenderProposalEditAccess(Request $request, TenderProposal $tenderProposal): void
    {
        if ($request->user()->canManage()) {
            return;
        }

        abort(403);
    }

    private function assignmentForUser(Request $request, TenderProposal $tenderProposal)
    {
        if ($request->user()->canViewPortfolio()) {
            return null;
        }

        return $tenderProposal->assignments()
            ->where('department_id', $request->user()->department_id)
            ->latest()
            ->first();
    }

    private function markAssignmentViewed($assignment): void
    {
        if (! $assignment) {
            return;
        }

        $assignment->update([
            'read_at' => $assignment->read_at ?? now(),
            'viewed_at' => now(),
            'workflow_status' => $assignment->workflow_status === 'Assigned' ? 'In Progress' : $assignment->workflow_status,
        ]);
    }

    private function nextTenderReference(): string
    {
        $lastCode = TenderProposal::query()
            ->where('tender_reference', 'like', 'TDR-%')
            ->orderByDesc('id')
            ->value('tender_reference');

        $number = $lastCode ? ((int) substr($lastCode, 4)) + 1 : 1;

        return sprintf('TDR-%03d', $number);
    }
}
