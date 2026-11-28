@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $ticket->ticket_number }} - {{ $ticket->title }}</h1>
            <p class="page-subtitle">{{ $ticket->status }} | {{ $ticket->priority }} priority | {{ $ticket->category?->name ?? 'Uncategorised' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canTriage)
                <form method="POST" action="{{ route('tickets.requisition', $ticket) }}">
                    @csrf
                    <button class="btn-primary" type="submit">Create Requisition</button>
                </form>
            @endif
            <a class="btn-secondary" href="{{ route('tickets.index') }}">Back to Tickets</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <div>
            <section class="panel">
                <h2 class="section-title">Request</h2>
                <p class="mt-4 whitespace-pre-line text-sm text-neutral-800">{{ $ticket->description ?: 'No description.' }}</p>
                @if($ticket->resolution_note)
                    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                        <strong>Resolution:</strong> {{ $ticket->resolution_note }}
                    </div>
                @endif
            </section>

            <section class="panel mt-6">
                <h2 class="section-title">Attachments</h2>
                <div class="mt-4 space-y-2 text-sm">
                    @forelse($ticket->documents as $document)
                        <a class="flex items-center justify-between rounded border border-neutral-200 px-3 py-2 hover:bg-neutral-50" href="{{ route('documents.download', $document) }}">
                            <span>{{ $document->title ?: $document->original_name }}</span>
                            <span class="text-xs text-neutral-500">{{ number_format($document->size / 1024, 1) }} KB</span>
                        </a>
                    @empty
                        <p class="empty">No attachments yet.</p>
                    @endforelse
                </div>

                <form class="mt-4" method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="block">
                        <span class="label">Add a screenshot or file</span>
                        <input class="input" type="file" name="document" required>
                    </label>
                    <button class="btn-secondary mt-3" type="submit">Upload</button>
                </form>
            </section>

            <section class="panel mt-6">
                <h2 class="section-title">Progress</h2>
                <p class="mt-3 text-sm text-neutral-800">
                    Current status: <strong>{{ $ticket->status }}</strong>
                    @if($ticket->updated_at)
                        <span class="text-xs text-neutral-500">(updated {{ $ticket->updated_at->diffForHumans() }})</span>
                    @endif
                </p>
                @if($ticket->requisitions->isNotEmpty())
                    <p class="mt-2 text-sm text-neutral-700">Sent to the directors for approval:</p>
                    <ul class="mt-1 space-y-1 text-sm text-neutral-700">
                        @foreach($ticket->requisitions as $requisition)
                            <li>
                                {{ $requisition->requisition_number }} —
                                <strong>{{ $requisition->status }}</strong>
                                @if($requisition->estimated_total)
                                    (E{{ number_format((float) $requisition->estimated_total, 2) }})
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if($canTriage)
                <section class="panel mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="section-title">Quotation &amp; Send to Directors</h2>
                        <span class="text-xs text-neutral-500">Price the fix, then send it to the directors for approval.</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($ticket->quotations as $quotation)
                            <div class="rounded border border-neutral-200 px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <strong>{{ $quotation->supplierLabel() }}</strong>
                                    <span class="font-semibold">E{{ number_format((float) $quotation->total, 2) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-neutral-600">
                                    @if($quotation->reference) Ref {{ $quotation->reference }} | @endif
                                    @if($quotation->valid_until) Valid until {{ $quotation->valid_until->toFormattedDateString() }} | @endif
                                    {{ $quotation->items->count() }} line(s)
                                </p>
                                @if($quotation->notes)
                                    <p class="mt-1 text-xs italic text-neutral-600">{{ $quotation->notes }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    @if($quotation->isSent())
                                        <span class="rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">
                                            Sent to directors
                                            @if($quotation->requisition)
                                                — {{ $quotation->requisition->requisition_number }}
                                            @endif
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('tickets.quotations.send', [$ticket, $quotation]) }}">
                                            @csrf
                                            <button class="btn-primary" type="submit">Send to Directors</button>
                                        </form>
                                        <form method="POST" action="{{ route('tickets.quotations.destroy', [$ticket, $quotation]) }}" onsubmit="return confirm('Remove this quotation?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-secondary" type="submit">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="empty">No quotation captured yet.</p>
                        @endforelse
                    </div>

                    <form class="mt-5 border-t border-neutral-200 pt-4" method="POST" action="{{ route('tickets.quotations.store', $ticket) }}">
                        @csrf
                        <div class="grid gap-3 md:grid-cols-4">
                            <label>
                                <span class="label">Supplier</span>
                                <select class="input" name="supplier_id">
                                    <option value="">Not listed</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label><span class="label">Supplier name (if not listed)</span><input class="input" name="supplier_name" placeholder="e.g. Cable Co"></label>
                            <label><span class="label">Reference</span><input class="input" name="reference" placeholder="Supplier quote number"></label>
                            <label><span class="label">Valid until</span><input class="input" type="date" name="valid_until"></label>
                        </div>

                        <div class="mt-3 overflow-x-auto">
                            <table class="data-table" data-quote-rows>
                                <thead><tr><th>Description</th><th class="w-24">Qty</th><th class="w-32">Unit Price</th><th class="w-20"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input class="input" name="items[0][description]" placeholder="What is being bought" required></td>
                                        <td><input class="input" type="number" step="0.01" min="0.01" name="items[0][quantity]" value="1" required></td>
                                        <td><input class="input" type="number" step="0.01" min="0" name="items[0][unit_price]" value="0" required></td>
                                        <td><button class="btn-secondary px-2 py-1" type="button" data-remove-quote-row>Remove</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button class="btn-secondary mt-3" type="button" data-add-quote-row>Add Line</button>

                        <label class="mt-3 block">
                            <span class="label">Notes for the directors</span>
                            <textarea class="input min-h-16" name="notes" placeholder="Anything they should know before approving"></textarea>
                        </label>

                        <div class="mt-3 flex justify-end">
                            <button class="btn-primary" type="submit">Save Quotation</button>
                        </div>
                    </form>
                </section>

                <script>
                    (function () {
                        var next = 1;
                        var addButton = document.querySelector('[data-add-quote-row]');
                        if (addButton) {
                            addButton.addEventListener('click', function () {
                                var body = document.querySelector('[data-quote-rows] tbody');
                                var row = document.createElement('tr');
                                row.innerHTML =
                                    '<td><input class="input" name="items[' + next + '][description]" placeholder="What is being bought" required></td>' +
                                    '<td><input class="input" type="number" step="0.01" min="0.01" name="items[' + next + '][quantity]" value="1" required></td>' +
                                    '<td><input class="input" type="number" step="0.01" min="0" name="items[' + next + '][unit_price]" value="0" required></td>' +
                                    '<td><button class="btn-secondary px-2 py-1" type="button" data-remove-quote-row>Remove</button></td>';
                                body.appendChild(row);
                                next++;
                            });
                        }

                        document.addEventListener('click', function (event) {
                            var remove = event.target.closest('[data-remove-quote-row]');
                            if (!remove) return;
                            var rows = document.querySelectorAll('[data-quote-rows] tbody tr');
                            if (rows.length > 1) {
                                remove.closest('tr').remove();
                            }
                        });
                    })();
                </script>
            @endif

            <section class="panel mt-6">
                <h2 class="section-title">Conversation</h2>
                <div class="mt-4 space-y-3">
                    @forelse($ticket->comments as $comment)
                        <div class="rounded border border-neutral-200 px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-neutral-500">
                                <strong class="text-neutral-800">{{ $comment->user?->name ?? 'System' }}</strong>
                                <span>
                                    {{ $comment->created_at?->toDayDateTimeString() }}
                                    @if($comment->is_internal)
                                        <em class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-amber-900">Internal</em>
                                    @endif
                                </span>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-neutral-800">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <p class="empty">No comments yet.</p>
                    @endforelse
                </div>

                <form class="mt-5" method="POST" action="{{ route('tickets.comments.store', $ticket) }}">
                    @csrf
                    <label>
                        <span class="label">Add a comment</span>
                        <textarea class="input min-h-20" name="body" required></textarea>
                    </label>
                    @if($canTriage)
                        <label class="mt-3 flex items-center gap-2 text-sm text-neutral-700">
                            <input class="rounded border-neutral-300" type="checkbox" name="is_internal" value="1">
                            Internal note (hidden from the requester)
                        </label>
                    @endif
                    <div class="mt-3 flex justify-end">
                        <button class="btn-primary" type="submit">Post Comment</button>
                    </div>
                </form>
            </section>
        </div>

        <aside>
            <section class="panel">
                <h2 class="section-title">Details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="label">Requester</dt><dd>{{ $ticket->requester?->name ?? '-' }}</dd></div>
                    <div><dt class="label">Department</dt><dd>{{ $ticket->department?->name ?? '-' }}</dd></div>
                    <div><dt class="label">Raised</dt><dd>{{ $ticket->created_at?->toDayDateTimeString() }}</dd></div>
                    <div><dt class="label">Assignee</dt><dd>{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                    @if($ticket->resolved_at)<div><dt class="label">Resolved</dt><dd>{{ $ticket->resolved_at->toDayDateTimeString() }}</dd></div>@endif
                    @if($ticket->closed_at)<div><dt class="label">Closed</dt><dd>{{ $ticket->closed_at->toDayDateTimeString() }}</dd></div>@endif
                </dl>
            </section>

            @if($canTriage)
                <section class="panel mt-6">
                    <h2 class="section-title">Triage</h2>

                    <form class="mt-4" method="POST" action="{{ route('tickets.assign', $ticket) }}">
                        @csrf
                        <label>
                            <span class="label">Assign to</span>
                            <select class="input" name="assigned_to">
                                <option value="">Unassigned</option>
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}" @selected((int) $ticket->assigned_to === $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="btn-secondary mt-3 w-full" type="submit">Save Assignment</button>
                    </form>

                    <form class="mt-5 border-t border-neutral-200 pt-4" method="POST" action="{{ route('tickets.status', $ticket) }}">
                        @csrf
                        <label>
                            <span class="label">Status</span>
                            <select class="input" name="status" required>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="mt-3 block">
                            <span class="label">Resolution note</span>
                            <textarea class="input min-h-16" name="resolution_note">{{ $ticket->resolution_note }}</textarea>
                        </label>
                        <label class="mt-3 block">
                            <span class="label">Reason (optional)</span>
                            <input class="input" name="reason" placeholder="Why the status changed">
                        </label>
                        <button class="btn-primary mt-3 w-full" type="submit">Update Status</button>
                    </form>
                </section>

                <section class="panel mt-6">
                    <h2 class="section-title">Linked Requisitions</h2>
                    <div class="mt-4 space-y-2 text-sm">
                        @forelse($ticket->requisitions as $requisition)
                            <a class="block rounded border border-neutral-200 px-3 py-2 hover:bg-neutral-50" href="{{ route('requisitions.show', $requisition) }}">
                                <strong>{{ $requisition->requisition_number }}</strong>
                                <span class="block text-xs text-neutral-500">{{ $requisition->status }} | E{{ number_format((float) $requisition->estimated_total, 2) }}</span>
                            </a>
                        @empty
                            <p class="empty">No requisitions raised from this ticket.</p>
                        @endforelse
                    </div>
                </section>

                <section class="panel mt-6">
                    <h2 class="section-title">Status History</h2>
                    <ol class="mt-4 space-y-2 text-xs text-neutral-600">
                        @forelse($ticket->statusHistory as $entry)
                            <li>
                                <strong class="text-neutral-800">{{ $entry->to_status }}</strong>
                                <span class="block">{{ $entry->actor?->name ?? 'System' }} | {{ $entry->created_at?->toDayDateTimeString() }}</span>
                                @if($entry->reason)<span class="block italic">{{ $entry->reason }}</span>@endif
                            </li>
                        @empty
                            <li>No status changes recorded.</li>
                        @endforelse
                    </ol>
                </section>
            @endif
        </aside>
    </div>
@endsection
