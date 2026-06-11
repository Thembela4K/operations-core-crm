@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Clients</h1>
            <p class="page-subtitle">Company client register for quotations, invoices, and billing contacts.</p>
        </div>
        @if(auth()->user()->canManageFinance())
            <a class="btn-primary" href="{{ route('clients.create') }}">New Client</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-[1fr_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search client name, code, email, or phone">
        <button class="btn-secondary" type="submit">Search</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Client</th><th>Contact</th><th>Activity</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td><strong>{{ $client->name }}</strong><br><span class="text-xs text-neutral-500">{{ $client->client_code }}</span></td>
                            <td>{{ $client->email ?: 'No email' }}<br><span class="text-xs text-neutral-500">{{ $client->phone ?: 'No phone' }}</span></td>
                            <td>{{ $client->sales_quotations_count }} quotations | {{ $client->invoices_count }} invoices<br><span class="text-xs text-neutral-500">{{ $client->contacts_count }} contacts</span></td>
                            <td>{{ $client->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-right"><a class="link" href="{{ route('clients.show', $client) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="empty">No clients yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $clients->links() }}</div>
    </section>
@endsection
