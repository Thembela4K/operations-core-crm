@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Timeline & Reminders</h1>
            <p class="page-subtitle">Project deadlines and quotation expiry dates due within {{ $daysBefore }} days.</p>
        </div>
        @if(auth()->user()->canManage())
            <form method="POST" action="{{ route('reminders.send-due') }}">
                @csrf
                <button class="btn-primary" type="submit">Send Due Reminders</button>
            </form>
        @endif
    </div>

    <section class="panel mt-6 overflow-x-auto">
        <h2 class="section-title">Upcoming and Overdue Deadlines</h2>
        <table class="data-table mt-4">
            <thead><tr><th>Type</th><th>Reference</th><th>Title</th><th>Status</th><th>Priority</th><th>Due</th><th>Days</th><th>Owner</th></tr></thead>
            <tbody>
                @forelse($upcomingItems as $item)
                    <tr>
                        <td>{{ $item['type'] }}</td>
                        <td>{{ $item['reference'] }}</td>
                        <td>{{ $item['title'] }}</td>
                        <td>{{ $item['status'] }}</td>
                        <td>{{ $item['priority'] }}</td>
                        <td>{{ $item['due_on']->toDateString() }}</td>
                        <td>{{ $item['days_left'] }}</td>
                        <td>{{ $item['owner'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No urgent reminders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="panel">
            <h2 class="section-title">Reminder Log</h2>
            <div class="mt-4 space-y-3">
                @forelse($reminderLogs as $log)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm">
                        <div class="font-medium">{{ class_basename($log->remindable_type) }} · {{ $log->status }}</div>
                        <div class="text-zinc-500">{{ $log->recipient }} · due {{ $log->due_on->toDateString() }} · {{ $log->created_at->toDayDateTimeString() }}</div>
                        @if($log->message)<div class="mt-1 text-rose-700">{{ $log->message }}</div>@endif
                    </div>
                @empty
                    <p class="empty">No reminder runs yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $reminderLogs->links() }}</div>
        </section>

        <section class="panel">
            <h2 class="section-title">Reminder Email Log</h2>
            <div class="mt-4 space-y-3">
                @forelse($emailLogs as $log)
                    <div class="rounded-md border border-zinc-200 p-3 text-sm">
                        <div class="font-medium">{{ $log->subject }} · {{ $log->status }}</div>
                        <div class="text-zinc-500">{{ $log->recipient }} · {{ $log->created_at->toDayDateTimeString() }}</div>
                        @if($log->message)<div class="mt-1 text-rose-700">{{ $log->message }}</div>@endif
                    </div>
                @empty
                    <p class="empty">No reminder email activity.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
