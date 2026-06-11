<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\CrmNotificationService;
use App\Services\FinanceNumberService;
use App\Services\RequisitionEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequisitionController extends Controller
{
    public function index(Request $request): View
    {
        $requisitions = Requisition::query()
            ->visibleTo($request->user())
            ->with(['department', 'supplier', 'requester', 'approver'])
            ->withCount(['items', 'documents'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('requisition_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('requester', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('department_id') && $request->user()->canViewRequisitions(), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('needed_by', '>=', $request->date('date_from')->toDateString()))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('needed_by', '<=', $request->date('date_to')->toDateString()))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('requisitions.index', [
            'requisitions' => $requisitions,
            'statuses' => Requisition::STATUSES,
            'categories' => Requisition::CATEGORIES,
            'priorities' => Requisition::PRIORITIES,
            'departments' => $this->departmentsFor($request),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        return view('requisitions.create', [
            'requisition' => new Requisition([
                'requisition_number' => $numbers->requisitionNumber(),
                'addressed_to' => 'Directors',
                'department_id' => $request->user()->department_id,
                'category' => 'Operational',
                'priority' => 'Medium',
                'status' => Requisition::STATUS_DRAFT,
                'needed_by' => now()->addWeek()->toDateString(),
            ]),
            'statuses' => Requisition::STATUSES,
            'categories' => Requisition::CATEGORIES,
            'priorities' => Requisition::PRIORITIES,
            'departments' => $this->departmentsFor($request),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, RequisitionEmailService $emails, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['attachments']);
        $data['requisition_number'] = $data['requisition_number'] ?: $numbers->requisitionNumber();
        $data['requested_by'] = $request->user()->id;
        $data['department_id'] = $request->user()->canViewRequisitions() ? $data['department_id'] : $request->user()->department_id;
        $data['status'] = $request->input('action') === 'submit' ? Requisition::STATUS_SUBMITTED : Requisition::STATUS_DRAFT;
        $data['submitted_at'] = $data['status'] === Requisition::STATUS_SUBMITTED ? now() : null;
        $items = $this->validatedItems($request);

        $requisition = DB::transaction(function () use ($request, $data, $items): Requisition {
            $requisition = Requisition::query()->create($data);
            $this->syncItems($requisition, $items);
            $this->storeAttachments($request, $requisition);

            return $requisition->fresh(['department', 'requester', 'items']);
        });

        if ($requisition->status === Requisition::STATUS_SUBMITTED) {
            $emails->notifySubmitted($requisition);
            $notifications->notifyApprovers('requisition_submitted', "Requisition {$requisition->requisition_number} submitted", $requisition->title, route('requisitions.show', $requisition));
            $audit->record('submitted', $requisition, "Requisition {$requisition->requisition_number} submitted.");
        } else {
            $audit->record('created', $requisition, "Requisition {$requisition->requisition_number} saved as draft.");
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', $requisition->status === Requisition::STATUS_SUBMITTED
            ? 'Requisition submitted and notification emails logged.'
            : 'Requisition draft saved.');
    }

    public function show(Request $request, Requisition $requisition): View
    {
        $this->authorizeView($request, $requisition);

        return view('requisitions.show', [
            'requisition' => $requisition->load(['department', 'supplier', 'requester', 'approver', 'releaser', 'items', 'documents.uploader', 'emailLogs']),
        ]);
    }

    public function edit(Request $request, Requisition $requisition): View
    {
        $this->authorizeEdit($request, $requisition);

        return view('requisitions.edit', [
            'requisition' => $requisition->load('items'),
            'statuses' => Requisition::STATUSES,
            'categories' => Requisition::CATEGORIES,
            'priorities' => Requisition::PRIORITIES,
            'departments' => $this->departmentsFor($request),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function print(Request $request, Requisition $requisition): View
    {
        $this->authorizeView($request, $requisition);

        return view('requisitions.print', [
            'requisition' => $requisition->load(['department', 'requester', 'approver', 'releaser', 'items']),
        ]);
    }

    public function update(Request $request, Requisition $requisition, RequisitionEmailService $emails, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeEdit($request, $requisition);

        $data = $this->validated($request, $requisition);
        unset($data['attachments']);
        $data['department_id'] = $request->user()->canViewRequisitions() ? $data['department_id'] : $request->user()->department_id;
        $items = $this->validatedItems($request);
        $submit = $request->input('action') === 'submit';

        DB::transaction(function () use ($request, $requisition, $data, $items, $submit): void {
            if ($submit) {
                $data['status'] = Requisition::STATUS_SUBMITTED;
                $data['submitted_at'] = $requisition->submitted_at ?: now();
                $data['decision_notes'] = null;
            }

            $requisition->update($data);
            $this->syncItems($requisition, $items);
            $this->storeAttachments($request, $requisition);
        });

        if ($submit) {
            $emails->notifySubmitted($requisition->fresh(['department', 'requester', 'items']));
            $notifications->notifyApprovers('requisition_submitted', "Requisition {$requisition->requisition_number} submitted", $requisition->title, route('requisitions.show', $requisition));
            $audit->record('submitted', $requisition, "Requisition {$requisition->requisition_number} submitted.");
        } else {
            $audit->record('updated', $requisition, "Requisition {$requisition->requisition_number} updated.");
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', $submit
            ? 'Requisition submitted and notification emails logged.'
            : 'Requisition updated.');
    }

    public function destroy(Request $request, Requisition $requisition): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $requisition->delete();

        return redirect()->route('requisitions.index')->with('success', 'Requisition deleted.');
    }

    public function submit(Request $request, Requisition $requisition, RequisitionEmailService $emails, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeEdit($request, $requisition);

        $requisition->update([
            'status' => Requisition::STATUS_SUBMITTED,
            'submitted_at' => $requisition->submitted_at ?: now(),
            'decision_notes' => null,
        ]);

        $emails->notifySubmitted($requisition->fresh(['department', 'requester', 'items']));
        $notifications->notifyApprovers('requisition_submitted', "Requisition {$requisition->requisition_number} submitted", $requisition->title, route('requisitions.show', $requisition));
        $audit->record('submitted', $requisition, "Requisition {$requisition->requisition_number} submitted.");

        return back()->with('success', 'Requisition submitted and notification emails logged.');
    }

    public function markInReview(Request $request, Requisition $requisition): RedirectResponse
    {
        if (! ($request->user()->canApproveRequisitions() || $request->user()->canReleaseRequisitionFunds())) {
            abort(403);
        }

        if (! in_array($requisition->status, [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW], true)) {
            return back()->with('warning', 'Only submitted requisitions can be marked in review.');
        }

        $requisition->update([
            'status' => Requisition::STATUS_IN_REVIEW,
            'reviewed_at' => $requisition->reviewed_at ?: now(),
        ]);

        return back()->with('success', 'Requisition marked in review.');
    }

    public function approve(Request $request, Requisition $requisition, RequisitionEmailService $emails, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canApproveRequisitions()) {
            abort(403);
        }

        if (! in_array($requisition->status, [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW], true)) {
            return back()->with('warning', 'Only submitted or in-review requisitions can be approved.');
        }

        $data = $request->validate(['decision_notes' => ['nullable', 'string']]);
        $requisition->update([
            'status' => Requisition::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'decision_notes' => $data['decision_notes'] ?? null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        $emails->notifyDecision($requisition->fresh(['department', 'requester', 'items']), 'Approved', 'Your requisition has been approved and is ready for funds release.');
        if ($requisition->requester) {
            $notifications->notifyUser($requisition->requester, 'requisition_approved', "Requisition {$requisition->requisition_number} approved", $requisition->title, route('requisitions.show', $requisition));
        }
        $audit->record('approved', $requisition, "Requisition {$requisition->requisition_number} approved.");

        return back()->with('success', 'Requisition approved.');
    }

    public function reject(Request $request, Requisition $requisition, RequisitionEmailService $emails, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canApproveRequisitions()) {
            abort(403);
        }

        if (! in_array($requisition->status, [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW], true)) {
            return back()->with('warning', 'Only submitted or in-review requisitions can be rejected.');
        }

        $data = $request->validate(['decision_notes' => ['required', 'string']]);
        $requisition->update([
            'status' => Requisition::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'decision_notes' => $data['decision_notes'],
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        $emails->notifyDecision($requisition->fresh(['department', 'requester', 'items']), 'Rejected', 'Your requisition has been rejected. Review the decision notes in the CRM.');
        if ($requisition->requester) {
            $notifications->notifyUser($requisition->requester, 'requisition_rejected', "Requisition {$requisition->requisition_number} rejected", $requisition->title, route('requisitions.show', $requisition));
        }
        $audit->record('rejected', $requisition, "Requisition {$requisition->requisition_number} rejected.");

        return back()->with('success', 'Requisition rejected.');
    }

    public function releaseFunds(Request $request, Requisition $requisition, RequisitionEmailService $emails, CrmNotificationService $notifications, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canReleaseRequisitionFunds()) {
            abort(403);
        }

        if ($requisition->status !== Requisition::STATUS_APPROVED) {
            return back()->with('warning', 'Only approved requisitions can have funds released.');
        }

        $data = $request->validate(['release_notes' => ['nullable', 'string']]);
        $requisition->update([
            'status' => Requisition::STATUS_FUNDS_RELEASED,
            'released_by' => $request->user()->id,
            'release_notes' => $data['release_notes'] ?? $requisition->release_notes,
            'funds_released_at' => now(),
        ]);

        $emails->notifyDecision($requisition->fresh(['department', 'requester', 'items']), 'Funds Released', 'Your approved requisition has been marked as funds released.');
        if ($requisition->requester) {
            $notifications->notifyUser($requisition->requester, 'requisition_funds_released', "Funds released for {$requisition->requisition_number}", $requisition->title, route('requisitions.show', $requisition));
        }
        $audit->record('funds_released', $requisition, "Funds released for requisition {$requisition->requisition_number}.");

        return back()->with('success', 'Requisition marked as funds released.');
    }

    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        if (! $this->canMutateOwnDepartment($request, $requisition) && ! $request->user()->canReleaseRequisitionFunds()) {
            abort(403);
        }

        if (in_array($requisition->status, [Requisition::STATUS_APPROVED, Requisition::STATUS_FUNDS_RELEASED], true)) {
            return back()->with('warning', 'Approved or funds-released requisitions cannot be cancelled.');
        }

        $requisition->update(['status' => Requisition::STATUS_CANCELLED]);

        return back()->with('success', 'Requisition cancelled.');
    }

    private function validated(Request $request, ?Requisition $requisition = null): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'requisition_number' => ['nullable', 'string', 'max:40', Rule::unique('requisitions', 'requisition_number')->ignore($requisition)],
            'addressed_to' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Requisition::CATEGORIES)],
            'priority' => ['required', Rule::in(Requisition::PRIORITIES)],
            'needed_by' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);
    }

    private function validatedItems(Request $request): array
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.payment_type' => ['required', 'in:Bank,Cash,Revenue,Other'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.source' => ['nullable', 'string'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        return collect($data['items'])
            ->filter(fn (array $item): bool => filled($item['description'] ?? null))
            ->values()
            ->all();
    }

    private function syncItems(Requisition $requisition, array $items): void
    {
        $requisition->items()->delete();
        $estimatedTotal = 0;
        $bankTotal = 0;
        $cashTotal = 0;
        $otherTotal = 0;

        foreach ($items as $index => $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitCost = (float) ($item['estimated_unit_cost'] ?? 0);
            $lineTotal = $quantity * $unitCost;
            $estimatedTotal += $lineTotal;
            $paymentType = $item['payment_type'];

            if ($paymentType === 'Bank') {
                $bankTotal += $lineTotal;
            } elseif (in_array($paymentType, ['Cash', 'Revenue'], true)) {
                $cashTotal += $lineTotal;
            } else {
                $otherTotal += $lineTotal;
            }

            $requisition->items()->create([
                'position' => $index + 1,
                'description' => $item['description'],
                'payment_type' => $paymentType,
                'quantity' => $quantity,
                'estimated_unit_cost' => $unitCost,
                'estimated_total' => $lineTotal,
                'source' => $item['source'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $requisition->update([
            'estimated_total' => $estimatedTotal,
            'bank_total' => $bankTotal,
            'cash_total' => $cashTotal,
            'other_total' => $otherTotal,
        ]);
    }

    private function storeAttachments(Request $request, Requisition $requisition): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("documents/requisitions/{$requisition->id}");

            $requisition->documents()->create([
                'category' => Document::CATEGORY_REQUISITION_ATTACHMENT,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function authorizeView(Request $request, Requisition $requisition): void
    {
        if ($request->user()->canViewRequisitions() || $requisition->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeEdit(Request $request, Requisition $requisition): void
    {
        if (! $requisition->isEditable()) {
            abort(403);
        }

        if ($this->canMutateOwnDepartment($request, $requisition) || $request->user()->canReleaseRequisitionFunds()) {
            return;
        }

        abort(403);
    }

    private function canMutateOwnDepartment(Request $request, Requisition $requisition): bool
    {
        return $requisition->department_id === $request->user()->department_id
            || $requisition->requested_by === $request->user()->id;
    }

    private function departmentsFor(Request $request)
    {
        if (! $request->user()->canViewRequisitions()) {
            return Department::query()->whereKey($request->user()->department_id)->get();
        }

        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }
}
