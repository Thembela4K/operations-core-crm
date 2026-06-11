@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $task->task_number }} - {{ $task->title }}</h1>
            <p class="page-subtitle">{{ $task->status }} | {{ $task->priority }} priority | {{ $task->department?->name ?: 'No department' }}</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('tasks.edit', $task) }}">Edit</a>
            <a class="btn-secondary" href="{{ route('tasks.index') }}">Back</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_0.8fr]">
        <section class="panel">
            <h2 class="section-title">Task Details</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2">
                <div><dt class="label">Assignee</dt><dd>{{ $task->assignee?->name ?: 'Unassigned' }}</dd></div>
                <div><dt class="label">Due Date</dt><dd>{{ $task->due_date?->toFormattedDateString() ?: 'No due date' }}</dd></div>
                <div><dt class="label">Created By</dt><dd>{{ $task->creator?->name ?: 'System' }}</dd></div>
                <div><dt class="label">Linked Record</dt><dd>{{ $task->taskable ? class_basename($task->taskable).' #'.$task->taskable->getKey() : 'Standalone' }}</dd></div>
                <div class="md:col-span-2"><dt class="label">Description</dt><dd class="whitespace-pre-line">{{ $task->description ?: 'No description.' }}</dd></div>
            </dl>

            <form class="mt-5 flex flex-wrap gap-2" method="POST" action="{{ route('tasks.status', $task) }}">
                @csrf
                <select class="input max-w-xs" name="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected($task->status === $status)>{{ $status }}</option>@endforeach</select>
                <button class="btn-primary" type="submit">Update Status</button>
            </form>
        </section>

        <section class="panel">
            <h2 class="section-title">Documents</h2>
            <form class="mt-4 grid gap-3" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="module" value="task">
                <input type="hidden" name="record_id" value="{{ $task->id }}">
                <input type="hidden" name="category" value="{{ \App\Models\Document::CATEGORY_SUPPORTING_DOCUMENT }}">
                <input class="input" type="file" name="document" required>
                <button class="btn-secondary" type="submit">Upload</button>
            </form>
            <div class="mt-4">@include('documents.preview-card', ['documents' => $task->documents])</div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <h2 class="section-title">Comments</h2>
            <form class="mt-4 grid gap-3" method="POST" action="{{ route('tasks.comments.store', $task) }}">
                @csrf
                <textarea class="input min-h-20" name="body" required></textarea>
                <button class="btn-primary justify-self-end" type="submit">Add Comment</button>
            </form>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($task->comments as $comment)
                    <div class="py-3 text-sm"><strong>{{ $comment->user?->name ?: 'System' }}</strong><p class="mt-1 whitespace-pre-line text-neutral-700">{{ $comment->body }}</p></div>
                @empty
                    <p class="empty">No comments.</p>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <h2 class="section-title">Audit History</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($task->auditLogs as $log)
                    <div class="py-3 text-sm"><strong>{{ $log->event }}</strong><p class="text-neutral-600">{{ $log->description }}</p><small>{{ $log->created_at->toDayDateTimeString() }}</small></div>
                @empty
                    <p class="empty">No audit history.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
