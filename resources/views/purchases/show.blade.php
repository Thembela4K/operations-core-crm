@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $purchase->purchase_number }} - {{ $purchase->title }}</h1>
            <p class="page-subtitle">{{ $purchase->status }} | E{{ number_format((float) $purchase->amount, 2) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('purchases.print', $purchase) }}" target="_blank">Print PO</a>
            @if(auth()->user()->canManageFinance())
                <a class="btn-secondary" href="{{ route('purchases.edit', $purchase) }}">Edit Purchase</a>
            @endif
        </div>
    </div>

    <section class="panel mt-6">
        <h2 class="section-title">Purchase Details</h2>
        <dl class="mt-4 grid gap-4 md:grid-cols-2">
            <div><dt class="label">Vendor</dt><dd>{{ $purchase->supplier?->name ?: 'No vendor' }}</dd></div>
            <div><dt class="label">Class</dt><dd>{{ $purchase->department?->name ?: 'Company-wide' }}</dd></div>
            <div><dt class="label">Requisition</dt><dd>{{ $purchase->requisition?->requisition_number ?: 'No requisition' }}</dd></div>
            <div><dt class="label">Purchase Date</dt><dd>{{ $purchase->purchase_date?->toFormattedDateString() ?: 'Not set' }}</dd></div>
            <div><dt class="label">Terms</dt><dd>{{ $purchase->terms ?: '—' }}</dd></div>
            <div><dt class="label">Expected Date</dt><dd>{{ $purchase->expected_date?->toFormattedDateString() ?: '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="label">Ship To</dt><dd class="whitespace-pre-line">{{ $purchase->ship_to ?: '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="label">Memo</dt><dd class="whitespace-pre-line">{{ $purchase->notes ?: 'No memo.' }}</dd></div>
        </dl>
    </section>

    <section class="panel mt-6 overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Line Items</h2>
            <span class="text-sm font-semibold text-neutral-700">Order Total: E{{ number_format((float) $purchase->amount, 2) }}</span>
        </div>
        <table class="data-table mt-4">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchase->items as $item)
                    <tr>
                        <td>{{ $item->catalogItem?->name ?? '—' }}</td>
                        <td class="whitespace-pre-line">{{ $item->description }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="text-right">E{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="text-right">E{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-neutral-500">No line items recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
