@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Quotations</h1>
            <p class="page-subtitle">Track opportunities, commercial status, risk, margin, expiry, assignments, and documents.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('quotations.export') }}">Export CSV</a>
            @if(auth()->user()->canManage())
                <a class="btn-primary" href="{{ route('quotations.create') }}">New Quotation</a>
            @endif
        </div>
    </div>

    <form method="GET" class="panel mt-6 grid gap-3 md:grid-cols-5">
        <input class="input md:col-span-2" name="search" placeholder="Search quotations" value="{{ request('search') }}">
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
                    <th>Risk</th>
                    <th>Win %</th>
                    <th>Score</th>
                    <th>Assigned</th>
                    <th>Valid Until</th>
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
                        <td>{{ $quotation->risk }}</td>
                        <td>{{ $quotation->win_probability_percent }}%</td>
                        <td>{{ $scoring->quotationScore($quotation) }}</td>
                        <td>{{ $quotation->latestAssignment?->department?->name ?? 'Unassigned' }}</td>
                        <td>{{ $quotation->valid_until->toDateString() }}</td>
                        <td class="text-right"><a class="link" href="{{ route('quotations.show', $quotation) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">No quotations found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $quotations->links() }}</div>
    </section>

    @if(auth()->user()->canManage())
        <section class="panel mt-6">
            <h2 class="section-title">Import Quotations</h2>
            <form method="POST" action="{{ route('quotations.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                @csrf
                <input class="input max-w-md" type="file" name="csv" accept=".csv,text/csv" required>
                <button class="btn-secondary" type="submit">Import CSV</button>
            </form>
        </section>
    @endif
@endsection
