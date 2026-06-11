@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">{{ $purchase->purchase_number }} - {{ $purchase->title }}</h1><p class="page-subtitle">{{ $purchase->status }} | E{{ number_format((float) $purchase->amount, 2) }}</p></div>@if(auth()->user()->canManageFinance())<a class="btn-secondary" href="{{ route('purchases.edit', $purchase) }}">Edit Purchase</a>@endif</div>
    <section class="panel mt-6"><h2 class="section-title">Purchase Details</h2><dl class="mt-4 grid gap-4 md:grid-cols-2"><div><dt class="label">Supplier</dt><dd>{{ $purchase->supplier?->name ?: 'No supplier' }}</dd></div><div><dt class="label">Department</dt><dd>{{ $purchase->department?->name ?: 'Company-wide' }}</dd></div><div><dt class="label">Requisition</dt><dd>{{ $purchase->requisition?->requisition_number ?: 'No requisition' }}</dd></div><div><dt class="label">Purchase Date</dt><dd>{{ $purchase->purchase_date?->toFormattedDateString() ?: 'Not set' }}</dd></div><div class="md:col-span-2"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $purchase->notes ?: 'No notes.' }}</dd></div></dl></section>
@endsection
