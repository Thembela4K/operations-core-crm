@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Quotation Requests</h1>
            <p class="page-subtitle">Track quotation requests, due dates, assignments, images, and returned submissions.</p>
        </div>
        @if(auth()->user()->canManage())
            <a class="btn-primary" href="{{ route('quotations.create') }}">New Quotation Request</a>
        @endif
    </div>

    <form method="GET" class="panel filter-panel">
        <input class="input filter-search" name="search" placeholder="Search quotation requests" value="{{ request('search') }}">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select class="input" name="priority">
            <option value="">All priorities</option>
            @foreach($priorities as $priority)
                <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>
            @endforeach
        </select>
        @if(auth()->user()->canViewPortfolio())
            <select class="input" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        @endif
        <select class="input" name="assignment_state">
            <option value="">Any assignment</option>
            <option value="assigned" @selected(request('assignment_state') === 'assigned')>Assigned</option>
            <option value="unassigned" @selected(request('assignment_state') === 'unassigned')>Unassigned</option>
        </select>
        <select class="input" name="workflow_status">
            <option value="">Any workflow</option>
            @foreach($workflowStatuses as $workflowStatus)
                <option value="{{ $workflowStatus }}" @selected(request('workflow_status') === $workflowStatus)>{{ $workflowStatus }}</option>
            @endforeach
        </select>
        <select class="input" name="deadline_window">
            <option value="">Any due window</option>
            <option value="overdue" @selected(request('deadline_window') === 'overdue')>Overdue</option>
            <option value="today" @selected(request('deadline_window') === 'today')>Today</option>
            <option value="next_5" @selected(request('deadline_window') === 'next_5')>Next 5 days</option>
            <option value="future" @selected(request('deadline_window') === 'future')>Future</option>
        </select>
        <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
        <div class="filter-actions">
            <button class="btn-secondary" type="submit">Filter</button>
            <a class="btn-secondary" href="{{ route('quotations.index') }}">Reset</a>
        </div>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quotation Request</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Assigned</th>
                    <th>Due / Valid Until</th>
                    <th>Documents</th>
                    <th>Submissions</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotations as $quotation)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $quotation->quotation_code }}</div>
                            <div class="text-zinc-600">{{ $quotation->opportunity }}</div>
                        </td>
                        <td>{{ $quotation->client }}</td>
                        <td>{{ $quotation->status }}</td>
                        <td>{{ $quotation->priority }}</td>
                        <td>{{ $quotation->latestAssignment?->department?->name ?? 'Unassigned' }}</td>
                        <td>{{ $quotation->valid_until->toDateString() }}</td>
                        <td>{{ $quotation->documents_count }}</td>
                        <td>{{ $quotation->submissions_count }}</td>
                        <td class="text-right"><a class="link" href="{{ route('quotations.show', $quotation) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No quotation requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $quotations->links() }}</div>
    </section>
@endsection
