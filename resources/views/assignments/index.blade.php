@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Assignment Center</h1>
            <p class="page-subtitle">
                Work and meetings assigned to you or your team. Everything here also appears in your Week Plan.
                @if($overdueCount > 0)
                    <span class="ml-2 rounded bg-red-50 px-2 py-1 text-xs font-semibold text-red-800">{{ $overdueCount }} overdue</span>
                @endif
            </p>
        </div>
        <a class="btn-primary" href="{{ route('assignments.create') }}">New Assignment</a>
    </div>

    @if($canSeeAll)
        <div class="mt-6 flex gap-2">
            <a class="btn-secondary {{ $scope === 'mine' ? 'ring-2 ring-[#087aa5]' : '' }}" href="{{ route('assignments.index', ['scope' => 'mine']) }}">My Assignments</a>
            <a class="btn-secondary {{ $scope === 'all' ? 'ring-2 ring-[#087aa5]' : '' }}" href="{{ route('assignments.index', ['scope' => 'all']) }}">All Assignments</a>
        </div>
    @endif

    <form class="panel mt-6 flex flex-wrap items-end gap-3" method="GET">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <label class="min-w-48 flex-1">
            <span class="label">Search</span>
            <input class="input" name="search" value="{{ request('search') }}" placeholder="Title or assignee">
        </label>
        <label class="w-44">
            <span class="label">Status</span>
            <select class="input" name="workflow_status">
                <option value="">Any</option>
                @foreach($workflowStatuses as $status)
                    <option value="{{ $status }}" @selected(request('workflow_status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="w-36">
            <span class="label">Kind</span>
            <select class="input" name="kind">
                <option value="">Any</option>
                @foreach($kinds as $kind)
                    <option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ ucfirst($kind) }}</option>
                @endforeach
            </select>
        </label>
        @if($canSeeAll)
            <label class="w-48">
                <span class="label">Department</span>
                <select class="input" name="department_id">
                    <option value="">Any</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="flex items-center gap-2 pb-2 text-sm">
            <input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> Overdue only
        </label>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Assignment</th>
                    <th>Kind</th>
                    @if($scope === 'all')
                        <th>Assignee</th>
                        <th>Department</th>
                    @endif
                    <th>Due / Meeting</th>
                    <th>Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $assignment)
                    <tr class="{{ $assignment->isOverdue() ? 'bg-red-50/40' : '' }}">
                        <td>
                            <a class="link" href="{{ route('assignments.show', $assignment) }}">{{ $assignment->label() }}</a>
                            @if($assignment->assignable)
                                <span class="block text-xs text-neutral-500">{{ class_basename($assignment->assignable_type) }}</span>
                            @endif
                        </td>
                        <td>{{ ucfirst($assignment->kind) }}</td>
                        @if($scope === 'all')
                            <td>{{ $assignment->assignedUser?->name ?? $assignment->assignee_name }}</td>
                            <td>{{ $assignment->department?->name ?? '—' }}</td>
                        @endif
                        <td>
                            @if($assignment->isMeeting() && $assignment->starts_at)
                                {{ $assignment->starts_at->format('D d M, H:i') }}
                            @elseif($assignment->due_date)
                                {{ $assignment->due_date->toFormattedDateString() }}
                                @if($assignment->isOverdue())
                                    <span class="block text-xs font-semibold text-red-700">Overdue</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td><strong>{{ $assignment->workflow_status }}</strong></td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                <a class="btn-secondary px-2 py-1 text-xs" href="{{ route('assignments.show', $assignment) }}">Open</a>
                                @if($assignment->workflow_status === 'Assigned')
                                    <form method="POST" action="{{ route('assignments.transition', $assignment) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="accept">
                                        <button class="btn-secondary px-2 py-1 text-xs" type="submit">Accept</button>
                                    </form>
                                @endif
                                @if($assignment->workflow_status === 'In Progress')
                                    <form method="POST" action="{{ route('assignments.transition', $assignment) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="complete">
                                        <button class="btn-secondary px-2 py-1 text-xs" type="submit">Complete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $scope === 'all' ? 7 : 5 }}"><p class="empty">No assignments.</p></td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $assignments->links() }}</div>
    </section>
@endsection
