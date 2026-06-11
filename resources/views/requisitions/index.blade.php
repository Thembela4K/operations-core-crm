@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Requisitions</h1>
            <p class="page-subtitle">Track internal fund requests, director approvals, funds release, documents, and notification history.</p>
        </div>
        <a class="btn-primary" href="{{ route('requisitions.create') }}">New Requisition</a>
    </div>

    <form method="GET" class="panel mt-6 grid gap-3 xl:grid-cols-[1.2fr_180px_180px_180px_190px_160px_160px_auto]">
        <input class="input" name="search" placeholder="Search requisitions, requester, or purpose" value="{{ request('search') }}">
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
        <select class="input" name="category">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
            @endforeach
        </select>
        @if(auth()->user()->canViewRequisitions())
            <select class="input" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="department_id" value="{{ auth()->user()->department_id }}">
        @endif
        <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
        <div class="flex gap-2">
            <button class="btn-secondary" type="submit">Filter</button>
            <a class="btn-secondary" href="{{ route('requisitions.index') }}">Reset</a>
        </div>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Requisition</th>
                        <th>Department</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Needed By</th>
                        <th>Estimated Total</th>
                        <th>Requester</th>
                        <th>Docs</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $requisition)
                        <tr>
                            <td>
                                <strong>{{ $requisition->requisition_number }}</strong>
                                <br>
                                <span class="text-xs text-neutral-500">{{ $requisition->title }}</span>
                            </td>
                            <td>{{ $requisition->department?->name ?? 'Company-wide' }}</td>
                            <td>{{ $requisition->supplier?->name ?: '-' }}</td>
                            <td>{{ $requisition->status }}</td>
                            <td>{{ $requisition->priority }}</td>
                            <td>{{ $requisition->needed_by?->toFormattedDateString() ?? 'Not set' }}</td>
                            <td>
                                E{{ number_format((float) $requisition->estimated_total, 2) }}
                                <div class="text-xs text-neutral-500">Bank E{{ number_format((float) $requisition->bank_total, 2) }} | Cash E{{ number_format((float) $requisition->cash_total, 2) }}</div>
                            </td>
                            <td>{{ $requisition->requester?->name ?? 'Unknown' }}</td>
                            <td>{{ $requisition->documents_count }}</td>
                            <td class="text-right"><a class="link" href="{{ route('requisitions.show', $requisition) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><p class="empty">No requisitions found.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $requisitions->links() }}</div>
    </section>
@endsection
