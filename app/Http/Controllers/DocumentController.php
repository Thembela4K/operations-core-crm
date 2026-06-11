<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\CrmTask;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Services\Assistant\DocumentTextExtractor;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $documents = Document::query()
            ->with(['uploader', 'documentable', 'textIndex'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('tags', 'like', "%{$search}%")
                        ->orWhereHas('textIndex', fn ($text) => $text->where('content', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('module'), fn ($query) => $this->applyModuleFilter($query, $request->string('module')->toString()))
            ->when($request->filled('linked_type'), fn ($query) => $this->applyLinkedTypeFilter($query, $request->string('linked_type')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')->toDateString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $documents->getCollection()->transform(function (Document $document) use ($request): ?Document {
            try {
                $this->authorizeRecordAccess($request, $document->documentable);

                return $document;
            } catch (\Throwable) {
                return null;
            }
        });

        $documents->setCollection($documents->getCollection()->filter()->values());

        return view('documents.index', [
            'documents' => $documents,
            'categories' => Document::CATEGORIES,
            'modules' => $this->documentModules(),
            'linkedTypes' => [
                'tender_proposal' => 'Tender Proposal Submissions',
                'quotation_request' => 'Quotation Request Submissions',
            ],
        ]);
    }

    public function store(Request $request, AuditLogService $audit, DocumentTextExtractor $textExtractor): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'in:tender_proposal,quotation,submission,expense,requisition,task,sales_quotation,invoice,payment'],
            'record_id' => ['required', 'integer'],
            'category' => ['nullable', Rule::in(array_keys(Document::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $record = $this->resolveRecord($data['module'], (int) $data['record_id']);
        $this->authorizeRecordMutation($request, $record);

        $file = $request->file('document');
        $path = $file->store("documents/{$data['module']}/{$record->id}");

        $document = $record->documents()->create([
            'category' => $data['category'] ?? Document::CATEGORY_OTHER,
            'title' => $data['title'] ?? null,
            'tags' => $data['tags'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        $textExtractor->index($document);
        $audit->record('document_uploaded', $document, "Document {$document->original_name} uploaded.");

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

    public function destroy(Request $request, Document $document, AuditLogService $audit): RedirectResponse
    {
        $document->load('documentable');
        $this->authorizeRecordMutation($request, $document->documentable);

        Storage::delete($document->path);
        $audit->record('document_deleted', $document, "Document {$document->original_name} deleted.");
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    private function resolveRecord(string $module, int $id): TenderProposal|Quotation|Submission|Expense|Requisition|CrmTask|SalesQuotation|Invoice|Payment
    {
        return match ($module) {
            'tender_proposal' => TenderProposal::query()->findOrFail($id),
            'submission' => Submission::query()->findOrFail($id),
            'expense' => Expense::query()->findOrFail($id),
            'requisition' => Requisition::query()->findOrFail($id),
            'task' => CrmTask::query()->findOrFail($id),
            'sales_quotation' => SalesQuotation::query()->findOrFail($id),
            'invoice' => Invoice::query()->findOrFail($id),
            'payment' => Payment::query()->findOrFail($id),
            default => Quotation::query()->findOrFail($id),
        };
    }

    private function applyModuleFilter($query, string $module): void
    {
        $type = match ($module) {
            'tender_proposal' => TenderProposal::class,
            'quotation_request' => Quotation::class,
            'submission' => Submission::class,
            'expense' => Expense::class,
            'requisition' => Requisition::class,
            'task' => CrmTask::class,
            'sales_quotation' => SalesQuotation::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            default => null,
        };

        if ($type) {
            $query->where('documentable_type', $type);
        }
    }

    private function applyLinkedTypeFilter($query, string $linkedType): void
    {
        $type = match ($linkedType) {
            'tender_proposal' => TenderProposal::class,
            'quotation_request' => Quotation::class,
            default => null,
        };

        if (! $type) {
            return;
        }

        $query->whereHasMorph('documentable', [Submission::class], fn ($submission) => $submission->where('submittable_type', $type));
    }

    private function documentModules(): array
    {
        return [
            'tender_proposal' => 'Tender Proposals',
            'quotation_request' => 'Quotation Requests',
            'submission' => 'Submissions',
            'requisition' => 'Requisitions',
            'task' => 'Tasks',
            'sales_quotation' => 'Sales Quotations',
            'invoice' => 'Invoices',
            'payment' => 'Payments',
            'expense' => 'Expenses',
        ];
    }

    private function authorizeRecordAccess(Request $request, TenderProposal|Quotation|Submission|Expense|Requisition|CrmTask|SalesQuotation|Invoice|Payment|null $record): void
    {
        if (! $record) {
            abort(404);
        }

        if ($record instanceof CrmTask) {
            if ($request->user()->canViewReports() || $record->department_id === $request->user()->department_id || $record->assigned_to === $request->user()->id || $record->created_by === $request->user()->id) {
                return;
            }

            abort(403);
        }

        if ($record instanceof SalesQuotation) {
            if ($request->user()->canViewReports() || $record->department_id === $request->user()->department_id) {
                return;
            }

            abort(403);
        }

        if ($record instanceof Invoice) {
            if ($request->user()->canViewReports() || $record->department_id === $request->user()->department_id) {
                return;
            }

            abort(403);
        }

        if ($record instanceof Payment) {
            if ($request->user()->canViewReports() || $request->user()->canManageFinance()) {
                return;
            }

            abort(403);
        }

        if ($record instanceof Requisition) {
            if ($request->user()->canViewRequisitions() || $record->department_id === $request->user()->department_id) {
                return;
            }

            abort(403);
        }

        if ($record instanceof Expense) {
            if ($request->user()->canViewReports() || $request->user()->canManageFinance()) {
                return;
            }

            abort(403);
        }

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

    private function authorizeRecordMutation(Request $request, TenderProposal|Quotation|Submission|Expense|Requisition|CrmTask|SalesQuotation|Invoice|Payment $record): void
    {
        if ($record instanceof CrmTask) {
            if ($request->user()->canManage() || $record->department_id === $request->user()->department_id || $record->assigned_to === $request->user()->id || $record->created_by === $request->user()->id) {
                return;
            }

            abort(403);
        }

        if ($record instanceof SalesQuotation || $record instanceof Invoice || $record instanceof Payment) {
            if ($request->user()->canManageFinance()) {
                return;
            }

            abort(403);
        }

        if ($record instanceof Requisition) {
            if ($request->user()->canReleaseRequisitionFunds() || $record->department_id === $request->user()->department_id) {
                return;
            }

            abort(403);
        }

        if ($record instanceof Expense) {
            if ($request->user()->canManageFinance()) {
                return;
            }

            abort(403);
        }

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
