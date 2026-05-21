<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Document;
use App\Models\Quotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $submissions = Submission::query()
            ->with(['submittable', 'department', 'submitter', 'assignment', 'documents'])
            ->when(! ($user->canReviewSubmissions() || $user->canManage()), function ($query) use ($user): void {
                $query->where('department_id', $user->department_id);
            })
            ->latest('submitted_at')
            ->paginate(15);

        return view('submissions.index', [
            'submissions' => $submissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'in:tender_proposal,quotation'],
            'record_id' => ['required', 'integer'],
            'assignment_id' => ['nullable', 'exists:assignments,id'],
            'status' => ['required', Rule::in(Submission::STATUSES)],
            'notes' => ['nullable', 'string'],
            'technical_document' => ['nullable', 'file', 'max:20480'],
            'financial_document' => ['nullable', 'file', 'max:20480'],
            'supporting_documents' => ['nullable', 'array'],
            'supporting_documents.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $record = $this->resolveRecord($data['module'], (int) $data['record_id']);
        $assignment = $this->resolveAssignment($request, $record, $data['assignment_id'] ?? null);
        $departmentId = $assignment?->department_id ?? $request->user()->department_id;

        if (! $departmentId) {
            return back()->withErrors(['status' => 'A submission must belong to a department.']);
        }

        $this->authorizeSubmissionAccess($request, $record, $assignment);

        $submission = $record->submissions()->create([
            'assignment_id' => $assignment?->id,
            'department_id' => $departmentId,
            'submitted_by' => $request->user()->id,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'submitted_at' => now(),
        ]);

        if ($request->hasFile('technical_document')) {
            $this->storeDocument($request->file('technical_document'), $submission, Document::CATEGORY_TECHNICAL_PROPOSAL, $request->user()->id);
        }

        if ($request->hasFile('financial_document')) {
            $this->storeDocument($request->file('financial_document'), $submission, Document::CATEGORY_FINANCIAL_PROPOSAL, $request->user()->id);
        }

        foreach ($request->file('supporting_documents', []) as $file) {
            if ($file) {
                $this->storeDocument($file, $submission, Document::CATEGORY_SUPPORTING_DOCUMENT, $request->user()->id);
            }
        }

        $workflowStatus = $submission->status === Submission::STATUS_FINISHED ? 'Finished Submitted' : 'Draft Submitted';
        $assignment?->update([
            'workflow_status' => $workflowStatus,
            'completed_at' => $submission->status === Submission::STATUS_FINISHED ? now() : $assignment->completed_at,
        ]);

        $record->update(['status' => $workflowStatus]);

        return back()->with('success', 'Submission saved.');
    }

    private function resolveRecord(string $module, int $id): TenderProposal|Quotation
    {
        return $module === 'tender_proposal'
            ? TenderProposal::query()->findOrFail($id)
            : Quotation::query()->findOrFail($id);
    }

    private function resolveAssignment(Request $request, TenderProposal|Quotation $record, ?int $assignmentId): ?Assignment
    {
        if ($assignmentId) {
            $assignment = Assignment::query()->findOrFail($assignmentId);
            abort_unless($assignment->assignable_type === $record::class && (int) $assignment->assignable_id === (int) $record->id, 403);

            return $assignment;
        }

        return $record->assignments()
            ->when(! $request->user()->canManage(), fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->latest()
            ->first();
    }

    private function authorizeSubmissionAccess(Request $request, TenderProposal|Quotation $record, ?Assignment $assignment): void
    {
        if ($request->user()->canManage()) {
            return;
        }

        if (! $assignment || $assignment->department_id !== $request->user()->department_id) {
            abort(403);
        }

        if (! $record->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }

    private function storeDocument($file, Submission $submission, string $category, int $userId): void
    {
        $path = $file->store("documents/submissions/{$submission->id}");

        $submission->documents()->create([
            'category' => $category,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }
}
