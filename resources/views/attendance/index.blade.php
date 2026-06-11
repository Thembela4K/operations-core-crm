@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Attendance</h1>
            <p class="page-subtitle">Clock in, clock out, corrections, and attendance reporting.</p>
        </div>
    </div>

    <section class="panel mt-6">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(260px,0.6fr)]">
            <div class="min-w-0">
                <span class="label">Today</span>
                <h2 class="text-xl font-semibold text-neutral-950">
                    @if($todayRecord)
                        {{ $todayRecord->status }}
                    @else
                        Not clocked in
                    @endif
                </h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="metric-card">
                        <span>Clock In</span>
                        <strong>{{ $todayRecord?->in_time?->format('H:i') ?: '-' }}</strong>
                    </div>
                    <div class="metric-card">
                        <span>Clock Out</span>
                        <strong>{{ $todayRecord?->out_time?->format('H:i') ?: '-' }}</strong>
                    </div>
                    <div class="metric-card">
                        <span>Duration</span>
                        <strong>{{ $todayRecord?->formattedDuration() ?: '0h 00m' }}</strong>
                    </div>
                </div>
            </div>

            <div class="flex flex-col justify-end gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                @if(! $todayRecord)
                    <form method="POST" action="{{ route('attendance.clock-in') }}">
                        @csrf
                        <button class="btn-primary w-full" type="submit">Clock In</button>
                    </form>
                @elseif(! $todayRecord->out_time)
                    <form method="POST" action="{{ route('attendance.clock-out') }}">
                        @csrf
                        <button class="btn-primary w-full" type="submit">Clock Out</button>
                    </form>
                @else
                    <span class="btn-secondary w-full">Completed {{ $todayRecord->formattedDuration() }}</span>
                @endif
                <p class="text-xs leading-5 text-neutral-500">Attendance changes are tracked in the audit trail when corrected by an authorized user.</p>
            </div>
        </div>
    </section>

    <div class="dashboard-metrics">
        <div class="metric-card"><span>Records</span><strong>{{ $attendanceRecordCount }}</strong><div class="metric-foot">Current filter</div></div>
        <div class="metric-card"><span>Completed</span><strong>{{ $attendanceCompletedCount }}</strong><div class="metric-foot">Closed clock records</div></div>
        <div class="metric-card"><span>Pending Review</span><strong>{{ $attendancePendingCount }}</strong><div class="metric-foot">Needs attention</div></div>
        <div class="metric-card"><span>Total Hours</span><strong>{{ $attendanceTotalHours }}h</strong><div class="metric-foot">Recorded time</div></div>
    </div>

    <form class="panel mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(5,minmax(130px,1fr))_auto]" method="GET">
        <label>
            <span class="label">Department</span>
            <select class="input" name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>@endforeach</select>
        </label>
        <label>
            <span class="label">Staff</span>
            <select class="input" name="user_id"><option value="">All staff</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int) request('user_id') === $user->id)>{{ $user->name }}</option>@endforeach</select>
        </label>
        <label>
            <span class="label">Status</span>
            <select class="input" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
        </label>
        <label>
            <span class="label">From</span>
            <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
        </label>
        <label>
            <span class="label">To</span>
            <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
        </label>
        <div class="flex items-end gap-2">
            <button class="btn-secondary w-full" type="submit">Filter</button>
        </div>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="px-4 py-4 sm:px-5">
            <h2 class="section-title">Attendance Register</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table attendance-table">
                <thead><tr><th>Staff</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Duration</th><th>Status</th><th>Correction</th></tr></thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>
                                <strong>{{ $record->user->name }}</strong>
                                <br>
                                <span class="text-xs text-neutral-500">{{ $record->department?->name ?: 'No department' }}</span>
                            </td>
                            <td>{{ $record->work_date->toFormattedDateString() }}</td>
                            <td>{{ $record->in_time?->format('H:i') ?: '-' }}</td>
                            <td>{{ $record->out_time?->format('H:i') ?: '-' }}</td>
                            <td>{{ $record->formattedDuration() }}</td>
                            <td><span class="status-pill">{{ $record->status }}</span></td>
                            <td>
                                @if(auth()->user()->canManageAttendance())
                                    <details class="correction-panel">
                                        <summary>Adjust</summary>
                                        <form method="POST" action="{{ route('attendance.correct', $record) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label><span class="label">Clock In</span><input class="input" type="datetime-local" name="in_time" value="{{ optional($record->in_time)->format('Y-m-d\TH:i') }}" required></label>
                                            <label><span class="label">Clock Out</span><input class="input" type="datetime-local" name="out_time" value="{{ optional($record->out_time)->format('Y-m-d\TH:i') }}"></label>
                                            <label class="sm:col-span-2"><span class="label">Correction Note</span><input class="input" name="correction_note" placeholder="Reason for correction"></label>
                                            <div class="sm:col-span-2 flex justify-end">
                                                <button class="btn-secondary" type="submit">Save Correction</button>
                                            </div>
                                        </form>
                                    </details>
                                @else
                                    <span class="text-sm text-neutral-500">{{ $record->correction_note ?: '-' }}</span>
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
