@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Tender Proposals</h1>
            <p class="page-subtitle">Track tender documents, due dates, assignments, and returned submissions.</p>
        </div>
        @if(auth()->user()->canManage())
            <a class="btn-primary" href="{{ route('tender-proposals.create') }}">New Tender Proposal</a>
        @endif
    </div>

    <form method="GET" class="panel mt-6 grid gap-3 xl:grid-cols-[1.2fr_170px_190px_170px_170px_150px_150px_auto]">
        <input class="input" name="search" placeholder="Search tender proposals" value="{{ request('search') }}">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
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
        <div class="flex gap-2">
            <button class="btn-secondary" type="submit">Filter</button>
            <a class="btn-secondary" href="{{ route('tender-proposals.index') }}">Reset</a>
        </div>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tender Proposal</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Due Date</th>
                    <th>Documents</th>
                    <th>Submissions</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenderProposals as $tenderProposal)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $tenderProposal->tender_reference }}</div>
                            <div class="text-zinc-600">{{ $tenderProposal->title }}</div>
                        </td>
                        <td>{{ $tenderProposal->status }}</td>
                        <td>{{ $tenderProposal->latestAssignment?->department?->name ?? 'Unassigned' }}</td>
                        <td>{{ $tenderProposal->closing_date->toDateString() }}</td>
                        <td>{{ $tenderProposal->documents_count }}</td>
                        <td>{{ $tenderProposal->submissions_count }}</td>
                        <td class="text-right"><a class="link" href="{{ route('tender-proposals.show', $tenderProposal) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No tender proposals found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $tenderProposals->links() }}</div>
    </section>
@endsection
