@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Week Plan Report</h1>
            <p class="page-subtitle">{{ $start->format('d M Y') }} &ndash; {{ $end->format('d M Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('tasks.report', ['week' => $previousWeek]) }}">&larr; Previous week</a>
            @unless($isCurrentWeek)
                <a class="btn-secondary" href="{{ route('tasks.report') }}">Last week</a>
            @endunless
            <a class="btn-secondary" href="{{ route('tasks.report', ['week' => $nextWeek]) }}">Next week &rarr;</a>
            <a class="btn-primary" href="{{ route('tasks.index') }}">Back to Week Plan</a>
        </div>
    </div>

    <div class="dashboard-metrics mt-6">
        <div class="metric-card"><span>Entries</span><strong>{{ $entries->count() }}</strong><div class="metric-foot">Touched this week</div></div>
        <div class="metric-card"><span>Created</span><strong>{{ $createdCount }}</strong><div class="metric-foot">New this week</div></div>
        <div class="metric-card"><span>Completed</span><strong>{{ $completedCount }}</strong><div class="metric-foot">Closed this week</div></div>
        <div class="metric-card"><span>Due</span><strong>{{ $dueCount }}</strong><div class="metric-foot">Deadline in this week</div></div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_320px]">
        <section class="panel overflow-x-auto">
            <h2 class="section-title">Entries</h2>
            <table class="data-table mt-4">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assignee</th>
                        <th>Due</th>
                        <th>Days</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $task)
                        <tr>
                            <td><a class="text-link" href="{{ route('tasks.show', $task) }}">{{ $task->task_number }}</a></td>
                            <td>{{ $task->title }}</td>
                            <td>{{ $task->status }}</td>
                            <td>{{ $task->priority }}</td>
                            <td>{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                            <td>{{ $task->due_date?->toFormattedDateString() ?? '—' }}</td>
                            <td>{{ $task->duration_days ? $task->duration_days.'d' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">Nothing was created, due or completed in this week.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <aside>
            <section class="panel">
                <h2 class="section-title">By Status</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse($statusCounts as $status => $count)
                        <li class="flex items-center justify-between"><span>{{ $status }}</span><strong>{{ $count }}</strong></li>
                    @empty
                        <li class="empty">No entries.</li>
                    @endforelse
                </ul>
            </section>

            <section class="panel mt-6">
                <h2 class="section-title">By Person</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse($byAssignee as $person => $count)
                        <li class="flex items-center justify-between"><span>{{ $person }}</span><strong>{{ $count }}</strong></li>
                    @empty
                        <li class="empty">No entries.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
@endsection
