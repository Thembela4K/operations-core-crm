@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Item Catalog</h1>
            <p class="page-subtitle">Reusable services and products for company sales quotations and invoices.</p>
        </div>
        @if(auth()->user()->canManageFinance() || auth()->user()->hasRole(\App\Models\User::ROLE_DEPARTMENT_USER))
            <a class="btn-primary" href="{{ route('catalog-items.create') }}">New Item</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-[1fr_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search item name or description">
        <button class="btn-secondary" type="submit">Search</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Item</th><th>Type</th><th>Department</th><th>Price</th><th>VAT</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td><strong>{{ $item->name }}</strong><br><span class="text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($item->description, 90) }}</span></td>
                            <td>{{ $types[$item->type] ?? $item->type }}</td>
                            <td>{{ $item->department?->name ?? 'Shared' }}</td>
                            <td>E{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td>{{ $item->taxable ? '15%' : 'No VAT' }}</td>
                            <td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-right"><a class="link" href="{{ route('catalog-items.edit', $item) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty">No catalog items yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $items->links() }}</div>
    </section>
@endsection
