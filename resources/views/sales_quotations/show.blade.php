@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $salesQuotation->quotation_number }}</h1>
            <p class="page-subtitle">{{ $salesQuotation->title }} | {{ $salesQuotation->client->name }} | {{ $salesQuotation->status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('sales-quotations.print', $salesQuotation) }}" target="_blank">Print</a>
            <a class="btn-secondary" href="{{ route('sales-quotations.pdf', $salesQuotation) }}">PDF</a>
            @if(in_array($salesQuotation->status, [\App\Models\SalesQuotation::STATUS_DRAFT, \App\Models\SalesQuotation::STATUS_REJECTED], true) && (auth()->user()->canManageFinance() || $salesQuotation->department_id === auth()->user()->department_id))
                <a class="btn-secondary" href="{{ route('sales-quotations.edit', $salesQuotation) }}">Edit</a>
                <form method="POST" action="{{ route('sales-quotations.submit', $salesQuotation) }}">@csrf<button class="btn-primary" type="submit">Submit for Approval</button></form>
            @endif
            @if(auth()->user()->canManageFinance() && in_array($salesQuotation->status, [\App\Models\SalesQuotation::STATUS_APPROVED, \App\Models\SalesQuotation::STATUS_SENT], true))
                <form method="POST" action="{{ route('sales-quotations.email', $salesQuotation) }}">@csrf<button class="btn-secondary" type="submit">Email Client</button></form>
                <form method="POST" action="{{ route('sales-quotations.mark-sent', $salesQuotation) }}">@csrf<button class="btn-secondary" type="submit">Mark Sent</button></form>
            @endif
            @if(auth()->user()->canManageFinance() && in_array($salesQuotation->status, [\App\Models\SalesQuotation::STATUS_APPROVED, \App\Models\SalesQuotation::STATUS_SENT, \App\Models\SalesQuotation::STATUS_ACCEPTED], true))
                <form method="POST" action="{{ route('sales-quotations.convert-to-invoice', $salesQuotation) }}">@csrf<button class="btn-primary" type="submit">Convert to Invoice</button></form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="panel">
            <h2 class="section-title">Quotation Lines</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Discount</th><th>VAT</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($salesQuotation->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                <td>E{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>E{{ number_format((float) $item->discount_amount, 2) }}</td>
                                <td>E{{ number_format((float) $item->vat_amount, 2) }}</td>
                                <td>E{{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="panel">
                <h2 class="section-title">Totals</h2>
                <div class="finance-total-box mt-4 w-full">
                    <div><span>Subtotal</span><strong>E{{ number_format((float) $salesQuotation->subtotal, 2) }}</strong></div>
                    <div><span>VAT {{ $vatRate }}%</span><strong>E{{ number_format((float) $salesQuotation->vat_total, 2) }}</strong></div>
                    <div><span>Total</span><strong>E{{ number_format((float) $salesQuotation->total, 2) }}</strong></div>
                </div>
            </section>

            @if(auth()->user()->canApproveFinance() && $salesQuotation->status === \App\Models\SalesQuotation::STATUS_SUBMITTED)
                <section class="panel">
                    <h2 class="section-title">Director Approval</h2>
                    <form class="mt-4 space-y-3" method="POST" action="{{ route('sales-quotations.approve', $salesQuotation) }}">
                        @csrf
                        <textarea class="input min-h-20" name="approval_notes" placeholder="Optional approval notes"></textarea>
                        <button class="btn-primary w-full" type="submit">Approve</button>
                    </form>
                    <form class="mt-3 space-y-3" method="POST" action="{{ route('sales-quotations.reject', $salesQuotation) }}">
                        @csrf
                        <textarea class="input min-h-20" name="approval_notes" placeholder="Required rejection reason" required></textarea>
                        <button class="btn-danger w-full" type="submit">Reject</button>
                    </form>
                </section>
            @endif

            <section class="panel">
                <h2 class="section-title">Workflow</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="label">Created By</dt><dd>{{ $salesQuotation->creator?->name ?? 'Unknown' }}</dd></div>
                    <div><dt class="label">Department</dt><dd>{{ $salesQuotation->department?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="label">Approved By</dt><dd>{{ $salesQuotation->approver?->name ?? 'Not approved' }}</dd></div>
                    <div><dt class="label">Approval Notes</dt><dd class="whitespace-pre-line">{{ $salesQuotation->approval_notes ?: 'None' }}</dd></div>
                    @if($salesQuotation->invoice)
                        <div><dt class="label">Invoice</dt><dd><a class="link" href="{{ route('invoices.show', $salesQuotation->invoice) }}">{{ $salesQuotation->invoice->invoice_number }}</a></dd></div>
                    @endif
                </dl>
            </section>
        </aside>
    </div>
@endsection
