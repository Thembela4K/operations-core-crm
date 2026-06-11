@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="page-title">{{ $supplier->name }}</h1><p class="page-subtitle">{{ $supplier->supplier_code }} | {{ $supplier->email ?: 'No email' }} | {{ $supplier->phone ?: 'No phone' }}</p></div>
        @if(auth()->user()->canManageFinance())<a class="btn-secondary" href="{{ route('suppliers.edit', $supplier) }}">Edit Supplier</a>@endif
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel xl:col-span-2"><h2 class="section-title">Supplier Details</h2><dl class="mt-4 grid gap-4 md:grid-cols-2"><div><dt class="label">Contact Person</dt><dd>{{ $supplier->contact_person ?: 'Not set' }}</dd></div><div><dt class="label">VAT Number</dt><dd>{{ $supplier->vat_number ?: 'Not set' }}</dd></div><div class="md:col-span-2"><dt class="label">Address</dt><dd class="whitespace-pre-line">{{ $supplier->address ?: 'No address.' }}</dd></div><div class="md:col-span-2"><dt class="label">Notes</dt><dd class="whitespace-pre-line">{{ $supplier->notes ?: 'No notes.' }}</dd></div></dl></section>
        <section class="panel"><h2 class="section-title">Activity</h2><div class="mt-4 grid gap-3"><div class="metric-card"><span>Expenses</span><strong>{{ $supplier->expenses->count() }}</strong></div><div class="metric-card"><span>Requisitions</span><strong>{{ $supplier->requisitions->count() }}</strong></div><div class="metric-card"><span>Purchases</span><strong>{{ $supplier->purchases->count() }}</strong></div></div></section>
    </div>
@endsection
