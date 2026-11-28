@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Leave</h1>
            <p class="page-subtitle">Apply for leave, track the annual working-day balance, and approve applications.</p>
        </div>
        <a class="btn-primary" href="{{ route('leave.create') }}">Apply for Leave</a>
    </div>

    <section class="panel mt-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <span class="label">Annual leave balance</span>
                <h2 class="text-xl font-semibold text-neutral-950">
                    {{ $balance['remaining'] }} of {{ $balance['total'] }} working days remaining
                </h2>
                <p class="mt-1 text-sm text-neutral-500">
                    {{ $balanceUser->name }} &middot; {{ $balance['year'] }}
                    @if($balance['pending'] > 0)
                        &middot; {{ $balance['pending'] }} pending approval
                    @endif
                </p>
            </div>
            @if($canViewAll && $pendingCount > 0)
                <span class="status-pill">{{ $pendingCount }} awaiting your approval</span>
            @endif
        </div>

        <div class="dashboard-metrics mt-4">
            <div class="metric-card"><span>Entitled</span><strong>{{ $balance['total'] }}</strong><div class="metric-foot">Days per calendar year</div></div>
            <div class="metric-card"><span>Taken</span><strong>{{ $balance['taken'] }}</strong><div class="metric-foot">Approved annual leave</div></div>
            <div class="metric-card"><span>Pending</span><strong>{{ $balance['pending'] }}</strong><div class="metric-foot">Awaiting decision</div></div>
            <div class="metric-card"><span>Available</span><strong>{{ $balance['available'] }}</strong><div class="metric-foot">Remaining minus pending</div></div>
        </div>
    </section>

    <form class="panel mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(4,minmax(130px,1fr))_auto]" method="GET">
        <label>
            <span class="label">Year</span>
            <input class="input" type="number" name="year" min="2020" max="2100" value="{{ request('year', $balance['year']) }}">
        </label>
        <label>
            <span class="label">Status</span>
            <select class="input" name="status">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        @if($canViewAll)
            <label>
                <span class="label">Staff</span>
                <select class="input" name="user_id">
                    <option value="">All staff</option>
                    @foreach($users as $staff)
                        <option value="{{ $staff->id }}" @selected((int) request('user_id') === $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="flex items-end gap-2">
            <button class="btn-secondary w-full" type="submit">Filter</button>
        </div>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="px-4 py-4 sm:px-5">
            <h2 class="section-title">Leave Register</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        @if($canViewAll)<th>Staff</th>@endif
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            @if($canViewAll)
                                <td>
                                    <strong>{{ $record->user->name }}</strong><br>
                                    <span class="text-xs text-neutral-500">{{ $record->department?->name ?: 'No department' }}</span>
                                </td>
                            @endif
                            <td>{{ $record->typeLabel() }}</td>
                            <td>{{ $record->from_date->toFormattedDateString() }}</td>
                            <td>{{ $record->to_date->toFormattedDateString() }}</td>
                            <td>{{ rtrim(rtrim(number_format($record->working_days, 1), '0'), '.') }}</td>
                            <td><span class="status-pill">{{ $record->status }}</span></td>
                            <td>{{ $record->applied_on->toFormattedDateString() }}</td>
                            <td><a class="link" href="{{ route('leave.show', $record) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canViewAll ? 8 : 7 }}"><p class="empty">No leave applications yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $records->links() }}</div>
    </section>

    @if($canManageHolidays)
        <section class="panel mt-6">
            <h2 class="section-title">Public Holidays</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Working days exclude weekends and these dates. Variable holidays (Incwala, Umhlanga) are proclaimed
                annually &mdash; add them here once gazetted.
            </p>

            <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(130px,180px)_minmax(0,1fr)_auto]" method="POST" action="{{ route('leave.holidays.store') }}">
                @csrf
                <label>
                    <span class="label">Date</span>
                    <input class="input" type="date" name="holiday_date" required>
                </label>
                <label>
                    <span class="label">Name</span>
                    <input class="input" name="name" placeholder="e.g. Incwala Day" required>
                </label>
                <div class="flex items-end">
                    <button class="btn-secondary" type="submit">Add</button>
                </div>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                @forelse($holidays as $holiday)
                    <form method="POST" action="{{ route('leave.holidays.destroy', $holiday) }}">
                        @csrf
                        @method('DELETE')
                        <button class="status-pill" type="submit" title="Remove">
                            {{ $holiday->holiday_date->toFormattedDateString() }} &middot; {{ $holiday->name }} &times;
                        </button>
                    </form>
                @empty
                    <p class="empty">No public holidays configured.</p>
                @endforelse
            </div>
        </section>
    @endif
@endsection
