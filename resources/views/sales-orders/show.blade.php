@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $order->order_number }} - {{ $order->title }}</h1>
            <p class="page-subtitle">{{ $order->status }} | E{{ number_format((float) $order->value, 2) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('sales-orders.print', $order) }}" target="_blank">Print Order</a>
            @if($order->canAcceptJobCards())
                <a class="btn-primary" href="{{ route('job-cards.create', ['sales_order_id' => $order->id]) }}">New Job Card</a>
            @endif
            @if(auth()->user()->canManage() || auth()->user()->canDraftFinance())
                <a class="btn-secondary" href="{{ route('sales-orders.edit', $order) }}">Edit Order</a>
            @endif
        </div>
    </div>

    <section class="panel mt-6">
        <h2 class="section-title">Order Details</h2>
        <dl class="mt-4 grid gap-4 md:grid-cols-2">
            <div><dt class="label">Client</dt><dd>{{ $order->client?->name ?? '-' }}</dd></div>
            <div><dt class="label">Department</dt><dd>{{ $order->department?->name ?? 'Unassigned' }}</dd></div>
            <div><dt class="label">Source Quotation</dt><dd>
                @if($order->quotation)
                    <a class="text-link" href="{{ route('sales-quotations.show', $order->quotation) }}">{{ $order->quotation->quotation_number }}</a>
                @else
                    Standalone order
                @endif
            </dd></div>
            <div><dt class="label">Client PO Reference</dt><dd>{{ $order->client_po_reference ?: '-' }}</dd></div>
            <div><dt class="label">Order Date</dt><dd>{{ $order->order_date?->toFormattedDateString() ?? '-' }}</dd></div>
            <div><dt class="label">Delivery Date</dt><dd>{{ $order->delivery_date?->toFormattedDateString() ?? '-' }}</dd></div>
            <div class="md:col-span-2"><dt class="label">Agreed Scope</dt><dd class="whitespace-pre-line">{{ $order->scope ?: '-' }}</dd></div>
            <div class="md:col-span-2"><dt class="label">Delivery Requirements</dt><dd class="whitespace-pre-line">{{ $order->delivery_requirements ?: '-' }}</dd></div>
            <div class="md:col-span-2"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $order->notes ?: 'No notes.' }}</dd></div>
        </dl>
    </section>

    <section class="panel mt-6 overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Job Cards</h2>
            <span class="text-sm text-neutral-600">{{ $order->jobCards->count() }} linked</span>
        </div>
        <table class="data-table mt-4">
            <thead>
                <tr>
                    <th>Job Card</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->jobCards as $jobCard)
                    <tr>
                        <td><a class="text-link" href="{{ route('job-cards.show', $jobCard) }}">{{ $jobCard->job_card_number }}</a></td>
                        <td>{{ $jobCard->title }}</td>
                        <td>{{ $jobCard->status }}</td>
                        <td>{{ $jobCard->due_date?->toFormattedDateString() ?? '-' }}</td>
                        <td>{{ $jobCard->invoice?->invoice_number ?? 'Not invoiced' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No job cards yet. Create one to start execution.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
