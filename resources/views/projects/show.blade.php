@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $project->title }}</h1>
            <p class="page-subtitle">{{ $project->user->name }} &middot; {{ $project->status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canEdit)
                <a class="btn-secondary" href="{{ route('projects.edit', $project) }}">Edit</a>
            @endif
            <a class="btn-secondary" href="{{ route('projects.index') }}">Back to Projects</a>
        </div>
    </div>

    <section class="panel mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Report</h2>
            <span class="status-pill">{{ $project->status }}</span>
        </div>

        <div class="mt-4">
            <div class="progress-row">
                <div class="progress-meta"><span>Progress</span><strong>{{ $project->progress_percent }}%</strong></div>
                <div class="progress-track"><i style="width: {{ $project->progress_percent }}%; background: #087aa5"></i></div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div><span class="label">Employee</span><p class="text-sm text-neutral-900">{{ $project->user->name }}</p></div>
            <div><span class="label">Department</span><p class="text-sm text-neutral-900">{{ $project->department?->name ?: 'No department' }}</p></div>
            <div><span class="label">Completed</span><p class="text-sm text-neutral-900">{{ $project->completed_at?->toFormattedDateString() ?: '-' }}</p></div>
            <div><span class="label">Added by</span><p class="text-sm text-neutral-900">{{ $project->creator?->name ?: '-' }}</p></div>
        </div>

        @if($project->comment)
            <div class="mt-4">
                <span class="label">Comment</span>
                <p class="whitespace-pre-line text-sm text-neutral-900">{{ $project->comment }}</p>
            </div>
        @endif

        @if($project->description)
            <div class="mt-4">
                <span class="label">Description</span>
                <p class="whitespace-pre-line text-sm text-neutral-900">{{ $project->description }}</p>
            </div>
        @endif
    </section>

    <section class="panel mt-6">
        <h2 class="section-title">Plan</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div><span class="label">Date</span><p class="text-sm text-neutral-900">{{ $project->planned_date?->toFormattedDateString() ?: '-' }}</p></div>
            <div><span class="label">By Whom</span><p class="text-sm text-neutral-900">{{ $project->by_whom ?: '-' }}</p></div>
            <div><span class="label">Time Frame</span><p class="text-sm text-neutral-900">{{ $project->time_frame ?: '-' }}</p></div>
            <div><span class="label">Target Date</span><p class="text-sm text-neutral-900">{{ $project->target_date?->toFormattedDateString() ?: '-' }}</p></div>
            <div><span class="label">Started On</span><p class="text-sm text-neutral-900">{{ $project->started_on?->toFormattedDateString() ?: '-' }}</p></div>
        </div>
    </section>

    @if($canEdit)
        <section class="panel mt-6">
            <form method="POST" action="{{ route('projects.destroy', $project) }}"
                  onsubmit="return confirm('Delete this project?');">
                @csrf
                @method('DELETE')
                <button class="btn-secondary" type="submit">Delete Project</button>
            </form>
        </section>
    @endif
@endsection
