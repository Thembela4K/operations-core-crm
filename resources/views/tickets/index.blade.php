@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">IT Service Desk</h1>
            <p class="page-subtitle">{{ $canTriage ? 'Triage and resolve employee IT requests.' : 'Your IT requests and their progress.' }}</p>
        </div>
        <a class="btn-primary" href="{{ route('tickets.create') }}">New Ticket</a>
    </div>

    <form class="panel mt-6 flex flex-wrap items-end gap-3" method="GET">
        <label class="flex-1 min-w-52">
            <span class="label">Search</span>
            <input class="input" name="search" value="{{ request('search') }}" placeholder="Ticket number or title">
        </label>
        <label class="w-52">
            <span class="label">Status</span>
            <select class="input" name="status">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="w-52">
            <span class="label">Category</span>
            <select class="input" name="category">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="w-40">
            <span class="label">Show</span>
            <select class="input" name="scope">
                <option value="">Everything I can see</option>
                <option value="open" @selected(request('scope') === 'open')>Open only</option>
                <option value="mine" @selected(request('scope') === 'mine')>Raised by me</option>
            </select>
        </label>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Requester</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assignee</th>
                    @if($canTriage)
                        <th class="text-right">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td><a class="text-link" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                        <td>{{ $ticket->title }}</td>
                        <td>{{ $ticket->category?->name ?? '-' }}</td>
                        <td>{{ $ticket->requester?->name ?? '-' }}</td>
                        <td>{{ $ticket->priority }}</td>
                        <td>
                            <strong>{{ $ticket->status }}</strong>
                            <span class="block text-xs text-neutral-500">Updated {{ $ticket->updated_at?->diffForHumans() }}</span>
                        </td>
                        <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                        @if($canTriage)
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    <form method="POST" action="{{ route('tickets.requisition', $ticket) }}">
                                        @csrf
                                        <button class="btn-secondary px-2 py-1 text-xs" type="submit" title="Raise a requisition for the directors">Requisition</button>
                                    </form>
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="Resolved">
                                        <button class="btn-secondary px-2 py-1 text-xs" type="submit" title="Mark this ticket resolved">Resolve</button>
                                    </form>
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="Delayed">
                                        <button class="btn-secondary px-2 py-1 text-xs" type="submit" title="Mark as delayed">Delay</button>
                                    </form>
                                    <form method="POST" action="{{ route('tickets.status', $ticket) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="Rejected">
                                        <button class="btn-secondary px-2 py-1 text-xs" type="submit" title="Reject this ticket">Rejected</button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canTriage ? 8 : 7 }}" class="empty">No tickets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $tickets->links() }}</div>
    </section>
@endsection
