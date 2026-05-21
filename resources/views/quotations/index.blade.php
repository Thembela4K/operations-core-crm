@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Quotations</h1>
            <p class="page-subtitle">Track quotation requests, due dates, assignments, images, and returned submissions.</p>
        </div>
        @if(auth()->user()->canManage())
            <a class="btn-primary" href="{{ route('quotations.create') }}">New Quotation</a>
        @endif
    </div>

    <form method="GET" class="panel mt-6 grid gap-3 md:grid-cols-[1fr_220px_220px_140px]">
        <input class="input" name="search" placeholder="Search quotations" value="{{ request('search') }}">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select class="input" name="priority">
            <option value="">All priorities</option>
            @foreach($priorities as $priority)
                <option @selected(request('priority') === $priority)>{{ $priority }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quotation</th>
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
                    <tr><td colspan="9" class="empty">No quotations found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $quotations->links() }}</div>
    </section>
@endsection
