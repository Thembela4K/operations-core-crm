@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Invoices</h1>
            <p class="page-subtitle">Reception-issued client invoices, balances, payment status, and print/email workflow.</p>
        </div>
        @if(auth()->user()->canManageFinance())
            <a class="btn-primary" href="{{ route('invoices.create') }}">New Invoice</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(170px,220px)_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search invoice or client">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Client</th><th>Status</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due</th><th></th></tr></thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><strong>{{ $invoice->invoice_number }}</strong><br><span class="text-xs text-neutral-500">{{ $invoice->department?->name ?? 'Unassigned' }}</span></td>
                            <td>{{ $invoice->client->name }}</td>
                            <td>{{ $invoice->status }}</td>
                            <td>E{{ number_format((float) $invoice->total, 2) }}</td>
                            <td>E{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                            <td>E{{ number_format((float) $invoice->balance_due, 2) }}</td>
                            <td>{{ $invoice->due_date->toFormattedDateString() }}</td>
                            <td class="text-right"><a class="link" href="{{ route('invoices.show', $invoice) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><p class="empty">No invoices yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $invoices->links() }}</div>
    </section>
@endsection
