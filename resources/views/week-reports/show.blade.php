@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $report->user?->name }} — Weekly Report</h1>
            <p class="page-subtitle">
                {{ $report->week_start?->format('d M Y') }} – {{ $report->week_end?->format('d M Y') }}
                | {{ $report->department?->name ?? 'No department' }}
                | {{ $report->status }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canEdit)
                <a class="btn-primary" href="{{ route('week-reports.edit', ['week' => $report->week_start?->toDateString()]) }}">Edit Report</a>
            @endif
            <a class="btn-secondary" href="{{ route('week-reports.index') }}">All reports</a>
        </div>
    </div>

    @php($lastWeek = $report->tasks->where('section', \App\Models\WeekPlanReport::SECTION_LAST_WEEK))
    @php($thisWeek = $report->tasks->where('section', \App\Models\WeekPlanReport::SECTION_THIS_WEEK))

    <section class="panel mt-6 overflow-x-auto">
        <h2 class="section-title">Last Week — How Far They Got</h2>
        <table class="data-table mt-4">
            <thead><tr><th>Task</th><th class="w-40">Progress</th><th>Notes</th></tr></thead>
            <tbody>
                @forelse($lastWeek as $row)
                    <tr>
                        <td>{{ $row->title }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded bg-neutral-200"><div class="h-2 bg-[#087aa5]" style="width: {{ $row->progress_percent }}%"></div></div>
                                <span class="text-xs text-neutral-600">{{ $row->progress_percent }}%</span>
                            </div>
                        </td>
                        <td>{{ $row->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3"><p class="empty">No tasks recorded for last week.</p></td></tr>
                @endforelse
            </tbody>
        </table>
        @if($report->last_week_progress)
            <div class="mt-4 rounded border border-neutral-200 px-4 py-3 text-sm">
                <strong class="block text-xs uppercase tracking-wide text-neutral-500">Summary</strong>
                <p class="mt-1 whitespace-pre-line">{{ $report->last_week_progress }}</p>
            </div>
        @endif
    </section>

    <section class="panel mt-6 overflow-x-auto">
        <h2 class="section-title">This Week — What They Are Doing</h2>
        <p class="page-subtitle">A forecast with expected dates, not a progress score.</p>
        <table class="data-table mt-4">
            <thead><tr><th>Task</th><th class="w-40">Expected dates</th><th class="w-28">Est. duration</th><th class="w-24">Carried over</th><th>Notes</th></tr></thead>
            <tbody>
                @forelse($thisWeek as $row)
                    <tr>
                        <td>{{ $row->title }}</td>
                        <td>
                            @if($row->planned_start || $row->planned_end)
                                {{ $row->planned_start?->format('d M') ?? '?' }} – {{ $row->planned_end?->format('d M') ?? '?' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($row->estimatedDays())
                                {{ $row->estimatedDays() }} day{{ $row->estimatedDays() === 1 ? '' : 's' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->carried_over ? 'Yes' : 'No' }}</td>
                        <td>{{ $row->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><p class="empty">No plan recorded for this week.</p></td></tr>
                @endforelse
            </tbody>
        </table>
        @if($report->this_week_plan)
            <div class="mt-4 rounded border border-neutral-200 px-4 py-3 text-sm">
                <strong class="block text-xs uppercase tracking-wide text-neutral-500">Plan</strong>
                <p class="mt-1 whitespace-pre-line">{{ $report->this_week_plan }}</p>
            </div>
        @endif
    </section>

    <section class="panel mt-6">
        <h2 class="section-title">Contributors</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($report->contributors as $contributor)
                <div class="rounded border border-neutral-200 px-4 py-3 text-sm">
                    <strong>{{ $contributor->displayName() }}</strong>
                    <span class="block text-xs text-neutral-500">
                        {{ $contributor->isOutsourced() ? ($contributor->organisation ?: 'Outsourced') : 'Employee' }}
                    </span>
                    @if($contributor->contribution)
                        <p class="mt-1 text-neutral-700">{{ $contributor->contribution }}</p>
                    @endif
                </div>
            @empty
                <p class="empty">No other contributors recorded.</p>
            @endforelse
        </div>
    </section>
@endsection
