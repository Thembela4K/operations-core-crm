@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $quotation->quotation_code }} - {{ $quotation->opportunity }}</h1>
            <p class="page-subtitle">Quote score {{ $score }} · {{ $quotation->status }} · {{ $quotation->priority }}</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('quotations.edit', $quotation) }}">Edit</a>
            @if(auth()->user()->canManage())
                <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" onsubmit="return confirm('Delete this quotation?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="panel lg:col-span-2">
            <h2 class="section-title">Details</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-3">
                <div><dt class="label">Client</dt><dd>{{ $quotation->client }}</dd></div>
                <div><dt class="label">Owner</dt><dd>{{ $quotation->owner }} {{ $quotation->owner_email ? "({$quotation->owner_email})" : '' }}</dd></div>
                <div><dt class="label">Risk</dt><dd>{{ $quotation->risk }}</dd></div>
                <div><dt class="label">Win Probability</dt><dd>{{ $quotation->win_probability_percent }}%</dd></div>
                <div><dt class="label">Quoted Amount</dt><dd>{{ number_format((float) $quotation->quoted_amount, 2) }}</dd></div>
                <div><dt class="label">Expected Cost</dt><dd>{{ number_format((float) $quotation->expected_cost, 2) }}</dd></div>
                <div><dt class="label">Issue Date</dt><dd>{{ $quotation->issue_date->toDateString() }}</dd></div>
                <div><dt class="label">Valid Until</dt><dd>{{ $quotation->valid_until->toDateString() }}</dd></div>
                <div><dt class="label">Rating</dt><dd>{{ $quotation->rating }}</dd></div>
                <div class="md:col-span-3"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $quotation->notes ?: 'No notes.' }}</dd></div>
            </dl>
        </section>

        <section class="panel">
            <h2 class="section-title">Documents</h2>
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="module" value="quotation">
                <input type="hidden" name="record_id" value="{{ $quotation->id }}">
                <input class="input" type="file" name="document" required>
                <button class="btn-secondary w-full" type="submit">Upload</button>
            </form>
            <div class="mt-4 space-y-2">
                @forelse($quotation->documents as $document)
                    <div class="flex items-center justify-between gap-2 rounded-md border border-zinc-200 p-2 text-sm">
                        <a class="link truncate" href="{{ route('documents.download', $document) }}">{{ $document->original_name }}</a>
                        <form method="POST" action="{{ route('documents.destroy', $document) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-rose-700" type="submit">Remove</button>
                        </form>
                    </div>
                @empty
                    <p class="empty">No documents.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="panel">
            <h2 class="section-title">Assignments</h2>
            <div class="mt-4 space-y-3">
                @forelse($quotation->assignments as $assignment)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm">
                        <div class="font-medium">{{ $assignment->department->name }} · {{ $assignment->assignee_name }}</div>
                        <div class="text-zinc-500">{{ $assignment->assignee_email }} · {{ $assignment->status }} · {{ $assignment->assigned_at?->toDayDateTimeString() }}</div>
                    </div>
                @empty
                    <p class="empty">No assignments yet.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2 class="section-title">Email Log</h2>
            <div class="mt-4 space-y-3">
                @forelse($quotation->emailLogs->sortByDesc('created_at')->take(8) as $log)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm">
                        <div class="font-medium">{{ ucfirst($log->category) }} · {{ $log->status }}</div>
                        <div class="text-zinc-500">{{ $log->recipient }} · {{ $log->created_at->toDayDateTimeString() }}</div>
                        @if($log->message)<div class="mt-1 text-rose-700">{{ $log->message }}</div>@endif
                    </div>
                @empty
                    <p class="empty">No email activity.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
