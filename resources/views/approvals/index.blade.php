@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Approvals</h1>
        <p class="page-subtitle">One inbox for director approvals, requisition review, and funds release.</p>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel xl:col-span-1">
            <h2 class="section-title">Sales Quotations</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($salesQuotations as $quotation)
                    <a class="list-row" href="{{ route('sales-quotations.show', $quotation) }}">
                        <span><strong>{{ $quotation->quotation_number }} - {{ $quotation->title }}</strong><small>{{ $quotation->client->name }} | E{{ number_format((float) $quotation->total, 2) }}</small></span>
                        <em>Review</em>
                    </a>
                @empty
                    <p class="empty">No sales quotations awaiting approval.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $salesQuotations->links() }}</div>
        </section>
        <section class="panel xl:col-span-1">
            <h2 class="section-title">Requisition Approval</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($requisitions as $requisition)
                    <a class="list-row" href="{{ route('requisitions.show', $requisition) }}">
                        <span><strong>{{ $requisition->requisition_number }} - {{ $requisition->title }}</strong><small>{{ $requisition->department?->name }} | E{{ number_format((float) $requisition->estimated_total, 2) }}</small></span>
                        <em>Review</em>
                    </a>
                @empty
                    <p class="empty">No requisitions awaiting approval.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $requisitions->links() }}</div>
        </section>
        <section class="panel xl:col-span-1">
            <h2 class="section-title">Funds Release</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($fundsRelease as $requisition)
                    <a class="list-row" href="{{ route('requisitions.show', $requisition) }}">
                        <span><strong>{{ $requisition->requisition_number }}</strong><small>{{ $requisition->department?->name }} | Approved</small></span>
                        <em>Release</em>
                    </a>
                @empty
                    <p class="empty">No approved requisitions waiting for funds release.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $fundsRelease->links() }}</div>
        </section>
    </div>
@endsection
