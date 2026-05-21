@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $project->project_code }} - {{ $project->name }}</h1>
            <p class="page-subtitle">Health score {{ $score }} · {{ $project->status }} · {{ $project->priority }}</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('projects.edit', $project) }}">Edit</a>
            @if(auth()->user()->canManage())
                <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?')">
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
                <div><dt class="label">Owner</dt><dd>{{ $project->owner }} {{ $project->owner_email ? "({$project->owner_email})" : '' }}</dd></div>
                <div><dt class="label">Risk</dt><dd>{{ $project->risk }}</dd></div>
                <div><dt class="label">Rating</dt><dd>{{ $project->rating }}</dd></div>
                <div><dt class="label">Progress</dt><dd>{{ $project->progress_percent }}%</dd></div>
                <div><dt class="label">Budget</dt><dd>{{ number_format((float) $project->budget, 2) }}</dd></div>
                <div><dt class="label">Timeline</dt><dd>{{ $project->start_date->toDateString() }} to {{ $project->deadline->toDateString() }}</dd></div>
                <div class="md:col-span-3"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $project->notes ?: 'No notes.' }}</dd></div>
            </dl>
        </section>

        <section class="panel">
            <h2 class="section-title">Documents</h2>
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="module" value="project">
                <input type="hidden" name="record_id" value="{{ $project->id }}">
                <input class="input" type="file" name="document" required>
                <button class="btn-secondary w-full" type="submit">Upload</button>
            </form>
            <div class="mt-4 space-y-2">
                @forelse($project->documents as $document)
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
                @forelse($project->assignments as $assignment)
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
                @forelse($project->emailLogs->sortByDesc('created_at')->take(8) as $log)
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
