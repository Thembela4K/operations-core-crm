@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Attendance</h1>
            <p class="page-subtitle">Clock in, clock out, corrections, and attendance reporting.</p>
        </div>
    </div>

    <section class="panel mt-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="section-title">Today</h2>
                <p class="page-subtitle">
                    @if($todayRecord)
                        {{ $todayRecord->status }} | In: {{ $todayRecord->in_time?->format('H:i') ?: '-' }} | Out: {{ $todayRecord->out_time?->format('H:i') ?: '-' }}
                    @else
                        You have not clocked in today.
                    @endif
                </p>
            </div>
            @if(! $todayRecord)
                <form method="POST" action="{{ route('attendance.clock-in') }}">@csrf<button class="btn-primary" type="submit">Clock In</button></form>
            @elseif(! $todayRecord->out_time)
                <form method="POST" action="{{ route('attendance.clock-out') }}">@csrf<button class="btn-primary" type="submit">Clock Out</button></form>
            @else
                <span class="btn-secondary">Completed {{ $todayRecord->formattedDuration() }}</span>
            @endif
        </div>
    </section>

    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[repeat(5,minmax(130px,1fr))_auto]" method="GET">
        <select class="input" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>@endforeach</select>
        <select class="input" name="user_id"><option value="">All staff</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int) request('user_id') === $user->id)>{{ $user->name }}</option>@endforeach</select>
        <select class="input" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
        <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Staff</th><th>Date</th><th>In</th><th>Out</th><th>Duration</th><th>Status</th><th>Correction</th></tr></thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ $record->user->name }}<br><span class="text-xs text-neutral-500">{{ $record->department?->name }}</span></td>
                            <td>{{ $record->work_date->toFormattedDateString() }}</td>
                            <td>{{ $record->in_time?->format('H:i') ?: '-' }}</td>
                            <td>{{ $record->out_time?->format('H:i') ?: '-' }}</td>
                            <td>{{ $record->formattedDuration() }}</td>
                            <td>{{ $record->status }}</td>
                            <td>
                                @if(auth()->user()->canManageAttendance())
                                    <form class="grid gap-2 min-w-[280px]" method="POST" action="{{ route('attendance.correct', $record) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input class="input" type="datetime-local" name="in_time" value="{{ optional($record->in_time)->format('Y-m-d\TH:i') }}" required>
                                        <input class="input" type="datetime-local" name="out_time" value="{{ optional($record->out_time)->format('Y-m-d\TH:i') }}">
                                        <input class="input" name="correction_note" placeholder="Correction note">
                                        <button class="btn-secondary" type="submit">Correct</button>
                                    </form>
                                @else
                                    {{ $record->correction_note ?: '-' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><p class="empty">No attendance records.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $records->links() }}</div>
    </section>
@endsection
