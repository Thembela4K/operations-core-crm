@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="page-title">Client Follow-ups</h1><p class="page-subtitle">Calls, meetings, notes, emails, and next actions.</p></div>
        <a class="btn-primary" href="{{ route('client-activities.create') }}">New Follow-up</a>
    </div>
    <form class="panel mt-6 grid gap-3 lg:grid-cols-[1fr_180px_180px_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search subject or client">
        <select class="input" name="type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select>
        <select class="input" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>
    <section class="panel mt-6 overflow-hidden p-0"><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Activity</th><th>Client</th><th>Responsible</th><th>Next Follow-up</th><th>Status</th><th></th></tr></thead><tbody>@forelse($activities as $activity)<tr><td><strong>{{ $activity->subject }}</strong><br><span class="text-xs text-neutral-500">{{ $activity->type }}</span></td><td>{{ $activity->client->name }}</td><td>{{ $activity->responsibleUser?->name ?: 'Unassigned' }}</td><td>{{ $activity->next_follow_up_date?->toFormattedDateString() ?: 'Not set' }}</td><td>{{ $activity->status }}</td><td class="text-right"><a class="link" href="{{ route('client-activities.edit', $activity) }}">Edit</a></td></tr>@empty<tr><td colspan="6"><p class="empty">No client activities found.</p></td></tr>@endforelse</tbody></table></div><div class="p-4">{{ $activities->links() }}</div></section>
@endsection
