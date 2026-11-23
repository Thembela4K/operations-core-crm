@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Projects</h1>
            <p class="page-subtitle">What each employee is working on, progress, completion status and notes &mdash; in the weekly Report &amp; Plan format.</p>
        </div>
        <a class="btn-primary" href="{{ route('projects.create') }}">Add Project</a>
    </div>

    <div class="dashboard-metrics mt-6">
        <div class="metric-card"><span>Projects</span><strong>{{ $metrics['total'] }}</strong><div class="metric-foot">Current filter</div></div>
        <div class="metric-card"><span>Open</span><strong>{{ $metrics['open'] }}</strong><div class="metric-foot">Not completed or cancelled</div></div>
        <div class="metric-card"><span>Completed</span><strong>{{ $metrics['completed'] }}</strong><div class="metric-foot">Marked done</div></div>
        <div class="metric-card"><span>Average Progress</span><strong>{{ $metrics['average'] }}%</strong><div class="metric-foot">Across filtered projects</div></div>
    </div>

    <form class="panel mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(4,minmax(130px,1fr))_auto]" method="GET">
        <label>
            <span class="label">Search</span>
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Activity or comment">
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
                <span class="label">Employee</span>
                <select class="input" name="user_id">
                    <option value="">All employees</option>
                    @foreach($users as $staff)
                        <option value="{{ $staff->id }}" @selected((int) request('user_id') === $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Department</span>
                <select class="input" name="department_id">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="flex items-end gap-2">
            <button class="btn-secondary w-full" type="submit">Filter</button>
        </div>
    </form>

    @if($canViewAll)
        {{-- Administrator view: grouped under each employee --}}
        <div class="mt-6 space-y-6">
            @forelse($grouped as $userId => $userProjects)
                @php($employee = $userProjects->first()->user)
                <section class="panel overflow-hidden p-0">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 px-4 py-3 sm:px-5">
                        <div>
                            <h2 class="section-title">{{ $employee?->name ?? 'Unknown employee' }}</h2>
                            <p class="text-xs text-neutral-500">
                                {{ $employee?->department?->name ?? 'No department' }}
                                &middot; {{ $userProjects->count() }} project{{ $userProjects->count() === 1 ? '' : 's' }}
                                &middot; {{ $userProjects->where('status', \App\Models\Project::STATUS_COMPLETED)->count() }} completed
                            </p>
                        </div>
                        <span class="status-pill">
                            {{ round($userProjects->avg('progress_percent')) }}% average progress
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Status</th>
                                    <th>%</th>
                                    <th>Comment</th>
                                    <th>Date</th>
                                    <th>By Whom</th>
                                    <th>Time Frame</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($userProjects as $project)
                                    <tr>
                                        <td>
                                            <strong>{{ $project->title }}</strong>
                                            @if($project->target_date)
                                                <br><span class="text-xs text-neutral-500">Target {{ $project->target_date->toFormattedDateString() }}</span>
                                            @endif
                                        </td>
                                        <td><span class="status-pill">{{ $project->status }}</span></td>
                                        <td>{{ $project->progress_percent }}%</td>
                                        <td class="max-w-[26ch]">{{ \Illuminate\Support\Str::limit($project->comment ?: '-', 90) }}</td>
                                        <td>{{ $project->planned_date?->toFormattedDateString() ?: '-' }}</td>
                                        <td>{{ $project->by_whom ?: '-' }}</td>
                                        <td>{{ $project->time_frame ?: '-' }}</td>
                                        <td><a class="link" href="{{ route('projects.show', $project) }}">View</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <section class="panel"><p class="empty">No projects found for this filter.</p></section>
            @endforelse
        </div>
    @else
        {{-- Employee view: own projects only --}}
        <section class="panel mt-6 overflow-hidden p-0">
            <div class="px-4 py-4 sm:px-5">
                <h2 class="section-title">My Projects</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Status</th>
                            <th>%</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>By Whom</th>
                            <th>Time Frame</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $project)
                            <tr>
                                <td>
                                    <strong>{{ $project->title }}</strong>
                                    @if($project->target_date)
                                        <br><span class="text-xs text-neutral-500">Target {{ $project->target_date->toFormattedDateString() }}</span>
                                    @endif
                                </td>
                                <td><span class="status-pill">{{ $project->status }}</span></td>
                                <td>{{ $project->progress_percent }}%</td>
                                <td class="max-w-[26ch]">{{ \Illuminate\Support\Str::limit($project->comment ?: '-', 90) }}</td>
                                <td>{{ $project->planned_date?->toFormattedDateString() ?: '-' }}</td>
                                <td>{{ $project->by_whom ?: '-' }}</td>
                                <td>{{ $project->time_frame ?: '-' }}</td>
                                <td><a class="link" href="{{ route('projects.show', $project) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><p class="empty">You have not added any projects yet.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="mt-4">{{ $projects->links() }}</div>
@endsection
