@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Tasks</h1>
            <p class="page-subtitle">Department work, deadlines, comments, files, and accountability.</p>
        </div>
        <a class="btn-primary" href="{{ route('tasks.create') }}">New Task</a>
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(140px,180px))_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search task number, title, or description">
        <select class="input" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
        <select class="input" name="priority"><option value="">All priorities</option>@foreach($priorities as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>@endforeach</select>
        <select class="input" name="assigned_to"><option value="">All assignees</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int) request('assigned_to') === $user->id)>{{ $user->name }}</option>@endforeach</select>
        <select class="input" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>@endforeach</select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Task</th><th>Owner</th><th>Deadline</th><th>Status</th><th>Priority</th><th></th></tr></thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td><strong>{{ $task->task_number }}</strong><br><span class="text-xs text-neutral-500">{{ $task->title }}</span></td>
                            <td>{{ $task->assignee?->name ?: 'Unassigned' }}<br><span class="text-xs text-neutral-500">{{ $task->department?->name ?: 'No department' }}</span></td>
                            <td>{{ $task->due_date?->toFormattedDateString() ?: 'No due date' }}</td>
                            <td>{{ $task->status }}</td>
                            <td>{{ $task->priority }}</td>
                            <td class="text-right"><a class="link" href="{{ route('tasks.show', $task) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><p class="empty">No tasks found.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $tasks->links() }}</div>
    </section>
@endsection
