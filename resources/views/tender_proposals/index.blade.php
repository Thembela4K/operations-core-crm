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

    <form method="GET" class="panel mt-6 grid gap-3 md:grid-cols-[1fr_220px_140px]">
        <input class="input" name="search" placeholder="Search tender proposals" value="{{ request('search') }}">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">Filter</button>
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
