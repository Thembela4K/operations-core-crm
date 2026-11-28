<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ]),
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
