@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="page-title">Notifications</h1><p class="page-subtitle">In-app notification history for assignments, approvals, tasks, and reminders.</p></div>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-secondary" type="submit">Mark All Read</button></form>
    </div>
    <section class="panel mt-6">
        <div class="divide-y divide-neutral-100">
            @forelse($notifications as $notification)
                <form class="list-row" method="POST" action="{{ route('notifications.read', $notification) }}">
                    @csrf
                    <span>
                        <strong>{{ $notification->title }}</strong>
                        <small>{{ $notification->body ?: ucfirst($notification->type) }} | {{ $notification->created_at->diffForHumans() }}</small>
                    </span>
                    <button class="{{ $notification->read_at ? 'btn-secondary' : 'btn-primary' }}" type="submit">{{ $notification->read_at ? 'Open' : 'Read' }}</button>
                </form>
            @empty
                <p class="empty">No notifications yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $notifications->links() }}</div>
    </section>
@endsection
