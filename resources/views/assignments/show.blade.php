@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $assignment->label() }}</h1>
            <p class="page-subtitle">
                {{ ucfirst($assignment->kind) }}
                | {{ $assignment->department?->name ?? 'No department' }}
                | {{ $assignment->assignedUser?->name ?? $assignment->assignee_name }}
                | <strong>{{ $assignment->workflow_status }}</strong>
                @if($assignment->isOverdue())
                    <span class="ml-2 rounded bg-red-50 px-2 py-1 text-xs font-semibold text-red-800">Overdue</span>
                @endif
            </p>
        </div>
        <a class="btn-secondary" href="{{ route('assignments.index') }}">All assignments</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
            <section class="panel">
                <h2 class="section-title">Details</h2>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div><dt class="label">Type</dt><dd>{{ ucfirst($assignment->kind) }}</dd></div>
                    <div><dt class="label">Assignee</dt><dd>{{ $assignment->assignedUser?->name ?? $assignment->assignee_name }}</dd></div>
                    <div><dt class="label">Department</dt><dd>{{ $assignment->department?->name ?? '—' }}</dd></div>
                    <div><dt class="label">Due date</dt><dd>{{ $assignment->due_date?->toFormattedDateString() ?? '—' }}</dd></div>
                    @if($assignment->isMeeting())
                        <div><dt class="label">Starts</dt><dd>{{ $assignment->starts_at?->format('D d M Y, H:i') ?? '—' }}</dd></div>
                        <div><dt class="label">Ends</dt><dd>{{ $assignment->ends_at?->format('D d M Y, H:i') ?? '—' }}</dd></div>
                        <div><dt class="label">Location</dt><dd>{{ $assignment->location ?: '—' }}</dd></div>
                    @endif
                    @if($assignment->assignable)
                        <div class="md:col-span-2"><dt class="label">Linked record</dt><dd>{{ class_basename($assignment->assignable_type) }} — {{ $assignment->assignable->title ?? $assignment->assignable->opportunity }}</dd></div>
                    @endif
                </dl>
                @if($assignment->instructions)
                    <div class="mt-4 rounded border border-neutral-200 px-4 py-3 text-sm">
                        <strong class="block text-xs uppercase tracking-wide text-neutral-500">Instructions</strong>
                        <p class="mt-1 whitespace-pre-line">{{ $assignment->instructions }}</p>
                    </div>
                @endif
            </section>

            <section class="panel">
                <h2 class="section-title">History</h2>
                <ol class="mt-4 space-y-3">
                    @forelse($assignment->events as $event)
                        <li class="border-l-2 border-[#087aa5] pl-3 text-sm">
                            <strong>{{ ucfirst($event->action) }}</strong>
                            @if($event->from_status || $event->to_status)
                                <span class="text-neutral-600">{{ $event->from_status }} → {{ $event->to_status }}</span>
                            @endif
                            <span class="block text-xs text-neutral-500">
                                {{ $event->actor?->name ?? 'System' }} | {{ $event->created_at?->format('d M Y H:i') }}
                            </span>
                            @if($event->notes)
                                <p class="mt-1 text-neutral-700">{{ $event->notes }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="empty">No history yet.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            @if($canAct)
                <section class="panel">
                    <h2 class="section-title">Workflow</h2>
                    <form class="mt-4 space-y-3" method="POST" action="{{ route('assignments.transition', $assignment) }}">
                        @csrf
                        <label>
                            <span class="label">Move to</span>
                            <select class="input" name="action">
                                <option value="accept">Accept</option>
                                <option value="start">Start (In Progress)</option>
                                <option value="draft">Submit draft</option>
                                <option value="finish">Submit finished</option>
                                <option value="complete">Complete</option>
                                <option value="cancel">Cancel</option>
                            </select>
                        </label>
                        <label><span class="label">Notes</span><textarea class="input min-h-16" name="notes"></textarea></label>
                        <button class="btn-primary w-full" type="submit">Update</button>
                    </form>
                </section>
            @endif

            @if($canAssign)
                <section class="panel">
                    <h2 class="section-title">Reassign</h2>
                    <form class="mt-4 space-y-3" method="POST" action="{{ route('assignments.reassign', $assignment) }}">
                        @csrf
                        <label>
                            <span class="label">Person</span>
                            <select class="input" name="assigned_user_id">
                                <option value="">Keep / choose department</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected($assignment->assigned_user_id === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="label">Department</span>
                            <select class="input" name="department_id">
                                <option value="">Keep</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected($assignment->department_id === $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label><span class="label">New due date</span><input class="input" type="date" name="due_date" value="{{ $assignment->due_date?->format('Y-m-d') }}"></label>
                        <label><span class="label">Reason</span><input class="input" name="notes"></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="assign_to_me" value="1"> Reassign to me</label>
                        <button class="btn-secondary w-full" type="submit">Reassign</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
@endsection
