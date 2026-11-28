<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketQuotation;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CrmNotificationService;
use App\Services\FinanceNumberService;
use App\Services\RequisitionEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * IT Service Desk (report section 4).
 *
 * Any employee may raise a ticket. IT triage, assignment, status changes and
 * the ticket-to-requisition hand-off require the triage role.
 */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $tickets = Ticket::query()
            ->visibleTo($user)
            ->with(['requester', 'department', 'category', 'assignee'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($query) => $query->where('ticket_category_id', $request->integer('category')))
            ->when($request->string('scope')->toString() === 'mine', fn ($query) => $query->where('requester_id', $user->id))
            ->when($request->string('scope')->toString() === 'open', fn ($query) => $query->whereIn('status', Ticket::OPEN_STATUSES))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'statuses' => Ticket::STATUSES,
            'categories' => TicketCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'canTriage' => $user->canTriageItTickets(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('tickets.create', [
            'ticket' => new Ticket([
                'requester_id' => $request->user()->id,
                'department_id' => $request->user()->department_id,
                'priority' => Ticket::PRIORITY_NORMAL,
            ]),
            'categories' => TicketCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'ticket_category_id' => ['nullable', 'exists:ticket_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
        ]);

        $ticket = Ticket::query()->create($data + [
            'ticket_number' => $numbers->ticketNumber(),
            'requester_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'status' => Ticket::STATUS_SUBMITTED,
            'created_by' => $request->user()->id,
        ]);

        $this->recordStatus($ticket, null, Ticket::STATUS_SUBMITTED, $request->user(), 'Ticket submitted');
        $audit->record('created', $ticket, "IT ticket {$ticket->ticket_number} submitted.");

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket '.$ticket->ticket_number.' submitted to IT.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeView($request, $ticket);

        $canTriage = $request->user()->canTriageItTickets();

        return view('tickets.show', [
            'ticket' => $ticket->load([
                'requester', 'department', 'category', 'assignee', 'creator',
                'requisitions', 'statusHistory.actor',
                'comments.user', 'documents',
                'quotations.items', 'quotations.supplier', 'quotations.requisition',
            ]),
            'suppliers' => $canTriage
                ? Supplier::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'canTriage' => $canTriage,
            'statuses' => Ticket::STATUSES,
            'staff' => $canTriage
                ? User::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function comment(Request $request, Ticket $ticket, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeView($request, $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $internal = $request->boolean('is_internal') && $request->user()->canTriageItTickets();

        TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => $internal,
        ]);

        $audit->record('commented', $ticket, "Comment added to IT ticket {$ticket->ticket_number}.");

        return back()->with('success', 'Comment added.');
    }

    public function assign(Request $request, Ticket $ticket, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeTriage($request);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $before = ['assigned_to' => $ticket->assigned_to];
        $fromStatus = $ticket->status;

        $ticket->update([
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => ($data['assigned_to'] ?? null) !== null && $ticket->status === Ticket::STATUS_SUBMITTED
                ? Ticket::STATUS_ASSIGNED
                : $ticket->status,
        ]);

        // Assignment that moves the ticket to Assigned is still a transition and
        // belongs in the immutable history.
        if ($ticket->status !== $fromStatus) {
            $this->recordStatus($ticket, $fromStatus, $ticket->status, $request->user(), 'Assigned to IT staff');
        }

        $audit->record('assigned', $ticket, "IT ticket {$ticket->ticket_number} assigned.", $before, ['assigned_to' => $ticket->assigned_to]);

        return back()->with('success', 'Ticket assignment updated.');
    }

    public function status(Request $request, Ticket $ticket, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeTriage($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $from = $ticket->status;

        $ticket->update([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? $ticket->resolution_note,
            'resolved_at' => $data['status'] === Ticket::STATUS_RESOLVED ? ($ticket->resolved_at ?? now()) : $ticket->resolved_at,
            'closed_at' => $data['status'] === Ticket::STATUS_CLOSED ? now() : ($data['status'] === Ticket::STATUS_REOPENED ? null : $ticket->closed_at),
        ]);

        $this->recordStatus($ticket, $from, $data['status'], $request->user(), $data['reason'] ?? null);
        $audit->record('status_changed', $ticket, "IT ticket {$ticket->ticket_number} moved to {$data['status']}.", ['status' => $from], ['status' => $data['status']]);

        return back()->with('success', 'Ticket status updated.');
    }

    /**
     * Start a requisition pre-populated from the ticket (report 4.6).
     */
    public function createRequisition(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTriage($request);

        return redirect()->route('requisitions.create', ['source_ticket_id' => $ticket->id]);
    }

    /**
     * Screenshots and supporting files attached to the ticket (report 4.2).
     */
    public function attach(Request $request, Ticket $ticket, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeView($request, $ticket);

        $request->validate([
            'document' => ['required', 'file', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('document');
        $path = $file->store("documents/ticket/{$ticket->id}");

        $document = $ticket->documents()->create([
            'category' => Document::CATEGORY_OTHER,
            'title' => $request->input('title') ?: null,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $audit->record('document_uploaded', $document, "Attachment {$document->original_name} added to IT ticket {$ticket->ticket_number}.");

        return back()->with('success', 'Attachment uploaded.');
    }

    /**
     * The IT Officer prices the ticket: supplier, reference and line items.
     */
    public function storeQuotation(Request $request, Ticket $ticket, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeTriage($request);

        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:80'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $quotation = DB::transaction(function () use ($ticket, $data, $request): TicketQuotation {
            $quotation = $ticket->quotations()->create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'reference' => $data['reference'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'prepared_by' => $request->user()->id,
            ]);

            $subtotal = 0.0;
            $position = 1;

            foreach ($data['items'] as $row) {
                $quantity = round((float) $row['quantity'], 2);
                $unitPrice = round((float) $row['unit_price'], 2);
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;

                $quotation->items()->create([
                    'description' => $row['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'position' => $position++,
                ]);
            }

            $quotation->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $quotation;
        });

        $audit->record('quotation_added', $quotation, "Quotation added to IT ticket {$ticket->ticket_number}.");

        return back()->with('success', 'Quotation saved. Send it to the directors when it is ready.');
    }

    public function destroyQuotation(Request $request, Ticket $ticket, TicketQuotation $quotation, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeTriage($request);

        if ($quotation->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($quotation->isSent()) {
            return back()->with('warning', 'That quotation has already gone to the directors and cannot be removed.');
        }

        $audit->record('quotation_removed', $quotation, "Quotation removed from IT ticket {$ticket->ticket_number}.");
        $quotation->delete();

        return back()->with('success', 'Quotation removed.');
    }

    /**
     * Send the priced quotation to the directors as a requisition.
     */
    public function sendQuotation(
        Request $request,
        Ticket $ticket,
        TicketQuotation $quotation,
        FinanceNumberService $numbers,
        RequisitionEmailService $emails,
        CrmNotificationService $notifications,
        AuditLogService $audit,
    ): RedirectResponse {
        $this->authorizeTriage($request);

        if ($quotation->ticket_id !== $ticket->id) {
            abort(404);
        }

        if ($quotation->isSent()) {
            return back()->with('warning', 'That quotation has already been sent to the directors.');
        }

        $requisition = DB::transaction(function () use ($ticket, $quotation, $request, $numbers): Requisition {
            $requisition = Requisition::query()->create([
                'source_ticket_id' => $ticket->id,
                'department_id' => $ticket->department_id,
                'supplier_id' => $quotation->supplier_id,
                'requested_by' => $request->user()->id,
                'requisition_number' => $numbers->requisitionNumber(),
                'addressed_to' => 'Directors',
                'title' => 'IT procurement: '.$ticket->title,
                'category' => 'Operational',
                'priority' => 'Medium',
                'status' => Requisition::STATUS_SUBMITTED,
                'needed_by' => $quotation->valid_until?->toDateString(),
                'estimated_total' => $quotation->total,
                'bank_total' => $quotation->total,
                'cash_total' => 0,
                'other_total' => 0,
                'purpose' => 'Raised from IT ticket '.$ticket->ticket_number.' ('.$ticket->title.'). Requested by '.($ticket->requester?->name ?? 'an employee').'.',
                'submitted_at' => now(),
            ]);

            $position = 1;

            foreach ($quotation->items as $item) {
                $requisition->items()->create([
                    'position' => $position++,
                    'description' => $item->description,
                    'payment_type' => 'Bank',
                    'quantity' => $item->quantity,
                    'estimated_unit_cost' => $item->unit_price,
                    'estimated_total' => $item->line_total,
                    'source' => $quotation->supplierLabel(),
                ]);
            }

            $quotation->update(['requisition_id' => $requisition->id, 'sent_at' => now()]);

            $from = $ticket->status;
            $ticket->update(['status' => Ticket::STATUS_AWAITING_APPROVAL]);
            $this->recordStatus($ticket, $from, Ticket::STATUS_AWAITING_APPROVAL, $request->user(), 'Quotation sent to directors');

            return $requisition;
        });

        $emails->notifySubmitted($requisition->fresh(['department', 'requester', 'items']));
        $notifications->notifyApprovers(
            'requisition_submitted',
            "Requisition {$requisition->requisition_number} submitted",
            $requisition->title,
            route('requisitions.show', $requisition),
        );
        $audit->record('quotation_sent', $quotation, "Quotation sent to directors for ticket {$ticket->ticket_number}.");

        return back()->with('success', "Sent to the directors as requisition {$requisition->requisition_number}.");
    }

    private function recordStatus(Ticket $ticket, ?string $from, string $to, ?User $actor, ?string $reason): void
    {
        TicketStatusHistory::query()->create([
            'ticket_id' => $ticket->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    private function authorizeView(Request $request, Ticket $ticket): void
    {
        $user = $request->user();

        if ($user->canTriageItTickets()
            || $ticket->requester_id === $user->id
            || $ticket->assigned_to === $user->id
            || $ticket->created_by === $user->id) {
            return;
        }

        abort(403);
    }

    private function authorizeTriage(Request $request): void
    {
        if (! $request->user()->canTriageItTickets()) {
            abort(403);
        }
    }
}
