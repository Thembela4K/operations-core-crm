@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="page-title">Suppliers</h1><p class="page-subtitle">Supplier register linked to expenses, requisitions, and purchase tracking.</p></div>
        @if(auth()->user()->canManageFinance())<a class="btn-primary" href="{{ route('suppliers.create') }}">New Supplier</a>@endif
    </div>
    <form class="panel mt-6 grid gap-3 md:grid-cols-[1fr_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search supplier code, name, contact, or email">
        <button class="btn-secondary" type="submit">Search</button>
    </form>
    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Supplier</th><th>Contact</th><th>Activity</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->name }}</strong><br><span class="text-xs text-neutral-500">{{ $supplier->supplier_code }}</span></td>
                            <td>{{ $supplier->contact_person ?: 'No contact' }}<br><span class="text-xs text-neutral-500">{{ $supplier->email ?: $supplier->phone ?: 'No contact details' }}</span></td>
                            <td>{{ $supplier->expenses_count }} expenses | {{ $supplier->requisitions_count }} requisitions | {{ $supplier->purchases_count }} purchases</td>
                            <td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-right"><a class="link" href="{{ route('suppliers.show', $supplier) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="empty">No suppliers found.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $suppliers->links() }}</div>
    </section>
@endsection
