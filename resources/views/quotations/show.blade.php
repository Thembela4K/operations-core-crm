@extends('layouts.app')

@section('content')
    @php
        $countdownDueDate = $assignmentForUser?->due_date ?? $quotation->latestAssignment?->due_date ?? $quotation->valid_until;
        $countdownLabel = ($assignmentForUser?->due_date || $quotation->latestAssignment?->due_date) ? 'Assignment Due Date' : 'Quotation Due Date';
        $countdownTarget = $countdownDueDate?->copy()->endOfDay()->toIso8601String();
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $quotation->quotation_code }} - {{ $quotation->opportunity }}</h1>
            <p class="page-subtitle">{{ $quotation->status }} | due {{ $quotation->valid_until->toDateString() }}</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->canManage())
                <a class="btn-secondary" href="{{ route('quotations.edit', $quotation) }}">Edit</a>
                <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" onsubmit="return confirm('Delete this quotation?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Delete</button>
                </form>
            @endif
        </div>
    </div>

    @if($countdownTarget)
        <section class="deadline-banner mt-6" data-countdown-target="{{ $countdownTarget }}">
            <div>
                <span class="deadline-kicker">{{ $countdownLabel }}</span>
                <strong>{{ $countdownDueDate->toFormattedDateString() }}</strong>
                <small>{{ $quotation->status }} | {{ $quotation->documents->count() }} documents</small>
            </div>
            <div class="deadline-countdown" aria-live="polite">
                <span data-countdown-value>Calculating...</span>
                <div class="deadline-countdown-grid">
                    <em><strong data-countdown-days>--</strong>Days</em>
                    <em><strong data-countdown-hours>--</strong>Hours</em>
                    <em><strong data-countdown-minutes>--</strong>Minutes</em>
                    <em><strong data-countdown-seconds>--</strong>Seconds</em>
                </div>
            </div>
        </section>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel xl:col-span-2">
            <h2 class="section-title">Quotation Details</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-3">
                <div><dt class="label">Client</dt><dd>{{ $quotation->client }}</dd></div>
                <div><dt class="label">Priority</dt><dd>{{ $quotation->priority }}</dd></div>
                <div><dt class="label">Status</dt><dd>{{ $quotation->status }}</dd></div>
                <div><dt class="label">Issue Date</dt><dd>{{ $quotation->issue_date->toDateString() }}</dd></div>
                <div><dt class="label">Due / Valid Until</dt><dd>{{ $quotation->valid_until->toDateString() }}</dd></div>
                <div><dt class="label">Documents</dt><dd>{{ $quotation->documents->count() }}</dd></div>
                <div class="md:col-span-3"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $quotation->notes ?: 'No notes.' }}</dd></div>
            </dl>
        </section>

        <section class="panel">
            <h2 class="section-title">Documents</h2>
            @if(auth()->user()->canManage())
                <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="module" value="quotation">
                    <input type="hidden" name="record_id" value="{{ $quotation->id }}">
                    <select class="input" name="category">
                        <option value="{{ \App\Models\Document::CATEGORY_QUOTATION_REQUEST }}">Quotation Request</option>
                        <option value="{{ \App\Models\Document::CATEGORY_QUOTATION_IMAGE }}">Quotation Image</option>
                        <option value="{{ \App\Models\Document::CATEGORY_OTHER }}">Other</option>
                    </select>
                    <input class="input" type="file" name="document" required>
                    <button class="btn-secondary w-full" type="submit">Upload</button>
                </form>
            @endif
            <div class="mt-4">
                @include('documents.preview-card', ['documents' => $quotation->documents])
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel">
            <h2 class="section-title">Assignments</h2>
            <div class="mt-4 space-y-3">
                @forelse($quotation->assignments as $assignment)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm">
                        <div class="font-medium">{{ $assignment->department->name }} | {{ $assignment->assignee_name }}</div>
                        <div class="text-zinc-500">{{ $assignment->workflow_status }} | due {{ $assignment->due_date?->toDateString() ?? 'not set' }}</div>
                        @if($assignment->instructions)<div class="mt-1 text-zinc-600">{{ $assignment->instructions }}</div>@endif
                    </div>
                @empty
                    <p class="empty">No assignments yet.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2 class="section-title">Submit Response</h2>
            @if($assignmentForUser)
                <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="module" value="quotation">
                    <input type="hidden" name="record_id" value="{{ $quotation->id }}">
                    <input type="hidden" name="assignment_id" value="{{ $assignmentForUser->id }}">
                    <select class="input" name="status" required>
                        @foreach(\App\Models\Submission::STATUSES as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                    <textarea class="input min-h-24" name="notes" placeholder="Submission notes"></textarea>
                    <label><span class="label">Technical Proposal</span><input class="input" type="file" name="technical_document"></label>
                    <label><span class="label">Financial Proposal</span><input class="input" type="file" name="financial_document"></label>
                    <label><span class="label">Supporting Documents / Images</span><input class="input" type="file" name="supporting_documents[]" multiple></label>
                    <button class="btn-primary w-full" type="submit">Submit Response</button>
                </form>
            @else
                <p class="empty">Only the assigned department can submit a response.</p>
            @endif
        </section>

        <section class="panel">
            <h2 class="section-title">Email Log</h2>
            <div class="mt-4 space-y-3">
                @forelse($quotation->emailLogs->sortByDesc('created_at')->take(8) as $log)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm">
                        <div class="font-medium">{{ ucfirst($log->category) }} | {{ $log->status }}</div>
                        <div class="text-zinc-500">{{ $log->recipient }} | {{ $log->created_at->toDayDateTimeString() }}</div>
                        @if($log->message)<div class="mt-1 text-rose-700">{{ $log->message }}</div>@endif
                    </div>
                @empty
                    <p class="empty">No email activity.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel mt-6">
        <h2 class="section-title">Submissions</h2>
        <div class="mt-4 space-y-3">
            @forelse($quotation->submissions->sortByDesc('submitted_at') as $submission)
                <div class="rounded-md border border-zinc-200 p-3 text-sm">
                    <div class="font-medium">{{ $submission->department->name }} | {{ $submission->status }}</div>
                    <div class="text-zinc-500">Submitted by {{ $submission->submitter?->name ?? 'Unknown' }} | {{ $submission->submitted_at?->toDayDateTimeString() }}</div>
                    @if($submission->notes)<div class="mt-2 whitespace-pre-line text-zinc-700">{{ $submission->notes }}</div>@endif
                    <div class="mt-3 space-y-1">
                        @foreach($submission->documents as $document)
                            <a class="block link" href="{{ route('documents.download', $document) }}">{{ \App\Models\Document::CATEGORIES[$document->category] ?? 'Document' }} - {{ $document->original_name }}</a>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="empty">No submissions yet.</p>
            @endforelse
        </div>
    </section>
@endsection
