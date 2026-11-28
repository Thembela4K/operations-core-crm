@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Sales Orders</h1>
            <p class="page-subtitle">Accepted quotations converted into committed work.</p>
        </div>
        @if(auth()->user()->canManage() || auth()->user()->canDraftFinance())
            <a class="btn-primary" href="{{ route('sales-orders.create') }}">New Sales Order</a>
        @endif
    </div>

    <form class="panel mt-6 flex flex-wrap items-end gap-3" method="GET">
        <label class="flex-1 min-w-56">
            <span class="label">Search</span>
            <input class="input" name="search" value="{{ request('search') }}" placeholder="Order number, title or client">
        </label>
        <label class="w-48">
            <span class="label">Status</span>
            <select class="input" name="status">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Client</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th class="text-right">Value</th>
                    <th class="text-right">Jobs</th>
                    <th>Order Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a class="text-link" href="{{ route('sales-orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->client?->name ?? '-' }}</td>
                        <td>{{ $order->title }}</td>
                        <td>{{ $order->status }}</td>
                        <td class="text-right">E{{ number_format((float) $order->value, 2) }}</td>
                        <td class="text-right">{{ $order->job_cards_count }}</td>
                        <td>{{ $order->order_date?->toFormattedDateString() ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No sales orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $orders->links() }}</div>
    </section>
@endsection
