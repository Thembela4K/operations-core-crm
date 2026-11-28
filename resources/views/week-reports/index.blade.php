@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Weekly Reports</h1>
            <p class="page-subtitle">Progress on last week's tasks, the plan for this week, and who contributed.</p>
        </div>
        <a class="btn-primary" href="{{ route('week-reports.edit') }}">Write This Week's Report</a>
    </div>

    <form class="panel mt-6 flex flex-wrap items-end gap-3" method="GET">
        <label class="flex-1 min-w-48">
            <span class="label">Search</span>
            <input class="input" name="search" value="{{ request('search') }}" placeholder="Employee name">
        </label>
        @if($canViewAll)
            <label class="w-52">
                <span class="label">Employee</span>
                <select class="input" name="user_id">
                    <option value="">All employees</option>
                    @foreach($users as $employee)
                        <option value="{{ $employee->id }}" @selected((int) request('user_id') === $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="w-52">
                <span class="label">Department</span>
                <select class="input" name="department_id">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Tasks</th>
                    <th>Contributors</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>{{ $report->week_start?->toFormattedDateString() }} – {{ $report->week_end?->toFormattedDateString() }}</td>
                        <td>{{ $report->user?->name ?? '—' }}</td>
                        <td>{{ $report->department?->name ?? '—' }}</td>
                        <td>{{ $report->tasks_count }}</td>
                        <td>{{ $report->contributors_count }}</td>
                        <td>{{ $report->status }}</td>
                        <td class="text-right"><a class="link" href="{{ route('week-reports.show', $report) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><p class="empty">No weekly reports yet.</p></td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $reports->links() }}</div>
    </section>
@endsection
