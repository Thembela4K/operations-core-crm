<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Quotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'in:tender_proposal,quotation,submission'],
            'record_id' => ['required', 'integer'],
            'category' => ['nullable', Rule::in(array_keys(Document::CATEGORIES))],
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $record = $this->resolveRecord($data['module'], (int) $data['record_id']);
        $this->authorizeRecordMutation($request, $record);

        $file = $request->file('document');
        $path = $file->store("documents/{$data['module']}/{$record->id}");

        $record->documents()->create([
            'category' => $data['category'] ?? Document::CATEGORY_OTHER,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $document->load('documentable');
        $this->authorizeRecordAccess($request, $document->documentable);

        return Storage::download($document->path, $document->original_name);
    }

    public function preview(Request $request, Document $document): StreamedResponse
    {
        $document->load('documentable');
        $this->authorizeRecordAccess($request, $document->documentable);

        abort_unless($document->isPreviewable(), 415);

        return Storage::response(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
            'inline',
        );
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $document->load('documentable');
        $this->authorizeRecordMutation($request, $document->documentable);

        Storage::delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    private function resolveRecord(string $module, int $id): TenderProposal|Quotation|Submission
    {
        return match ($module) {
            'tender_proposal' => TenderProposal::query()->findOrFail($id),
            'submission' => Submission::query()->findOrFail($id),
            default => Quotation::query()->findOrFail($id),
        };
    }

    private function authorizeRecordAccess(Request $request, TenderProposal|Quotation|Submission $record): void
    {
        if ($record instanceof Submission) {
            $this->authorizeSubmissionAccess($request, $record);

            return;
        }

        if ($request->user()->canViewPortfolio()) {
            return;
        }

        if (! $record->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }

    private function authorizeSubmissionAccess(Request $request, Submission $submission): void
    {
        if ($request->user()->canReviewSubmissions() || $request->user()->canManage()) {
            return;
        }

        if ($submission->department_id !== $request->user()->department_id) {
            abort(403);
        }
    }

    private function authorizeRecordMutation(Request $request, TenderProposal|Quotation|Submission $record): void
    {
        if ($record instanceof Submission) {
            if ($request->user()->canManage() || $record->department_id === $request->user()->department_id) {
                return;
            }

            abort(403);
        }

        if ($request->user()->canManage()) {
            return;
        }

        if (! $record->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }
}
