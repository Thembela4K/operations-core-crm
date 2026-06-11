@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $client->name }}</h1>
            <p class="page-subtitle">{{ $client->client_code }} | {{ $client->email ?: 'No email' }} | {{ $client->phone ?: 'No phone' }}</p>
        </div>
        @if(auth()->user()->canManageFinance())
            <a class="btn-secondary" href="{{ route('clients.edit', $client) }}">Edit Client</a>
        @endif
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel xl:col-span-2">
            <h2 class="section-title">Billing Details</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2">
                <div><dt class="label">Billing Email</dt><dd>{{ $client->billing_email ?: 'Not set' }}</dd></div>
                <div><dt class="label">VAT / Tax Number</dt><dd>{{ $client->vat_number ?: 'Not set' }}</dd></div>
                <div class="md:col-span-2"><dt class="label">Address</dt><dd class="whitespace-pre-line">{{ $client->address ?: 'No address captured.' }}</dd></div>
                <div class="md:col-span-2"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $client->notes ?: 'No notes.' }}</dd></div>
            </dl>
        </section>
        <section class="panel">
            <h2 class="section-title">Primary Contact</h2>
            @php($primary = $client->contacts->firstWhere('is_primary', true))
            @if($primary)
                <div class="mt-4 text-sm">
                    <strong>{{ $primary->name }}</strong>
                    <p class="text-neutral-500">{{ $primary->position }}</p>
                    <p>{{ $primary->email }}</p>
                    <p>{{ $primary->phone }}</p>
                </div>
            @else
                <p class="empty">No primary contact captured.</p>
            @endif
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <h2 class="section-title">Sales Quotations</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($client->salesQuotations as $quotation)
                    <a class="list-row" href="{{ route('sales-quotations.show', $quotation) }}">
                        <span><strong>{{ $quotation->quotation_number }}</strong><small>{{ $quotation->title }}</small></span>
                        <em>E{{ number_format((float) $quotation->total, 2) }}</em>
                    </a>
                @empty
                    <p class="empty">No sales quotations.</p>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <h2 class="section-title">Invoices</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($client->invoices as $invoice)
                    <a class="list-row" href="{{ route('invoices.show', $invoice) }}">
                        <span><strong>{{ $invoice->invoice_number }}</strong><small>{{ $invoice->status }}</small></span>
                        <em>E{{ number_format((float) $invoice->balance_due, 2) }}</em>
                    </a>
                @empty
                    <p class="empty">No invoices.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="section-title">Client Follow-ups</h2>
                <p class="page-subtitle">Calls, meetings, notes, and next actions tied to this client.</p>
            </div>
            <a class="btn-secondary" href="{{ route('client-activities.create', ['client_id' => $client->id]) }}">New Follow-up</a>
        </div>
        <div class="mt-4 divide-y divide-neutral-100">
            @forelse($client->activities as $activity)
                <a class="list-row" href="{{ route('client-activities.edit', $activity) }}">
                    <span>
                        <strong>{{ $activity->subject }}</strong>
                        <small>{{ $activity->type }} | {{ $activity->responsibleUser?->name ?: 'Unassigned' }}</small>
                    </span>
                    <em>{{ $activity->next_follow_up_date?->toFormattedDateString() ?: $activity->status }}</em>
                </a>
            @empty
                <p class="empty">No follow-ups captured.</p>
            @endforelse
        </div>
    </section>
@endsection
