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
