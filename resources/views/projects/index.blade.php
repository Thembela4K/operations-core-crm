@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Projects</h1>
            <p class="page-subtitle">Track status, risk, ratings, budgets, deadlines, assignments, and documents.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('projects.export') }}">Export CSV</a>
            @if(auth()->user()->canManage())
                <a class="btn-primary" href="{{ route('projects.create') }}">New Project</a>
            @endif
        </div>
    </div>

    <form method="GET" class="panel mt-6 grid gap-3 md:grid-cols-5">
        <input class="input md:col-span-2" name="search" placeholder="Search projects" value="{{ request('search') }}">
        <select class="input" name="status">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select class="input" name="priority">
            <option value="">All priorities</option>
            @foreach($priorities as $priority)
                <option @selected(request('priority') === $priority)>{{ $priority }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Risk</th>
                    <th>Progress</th>
                    <th>Score</th>
                    <th>Assigned</th>
                    <th>Deadline</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $project->project_code }}</div>
                            <div class="text-zinc-600">{{ $project->name }}</div>
                        </td>
                        <td>{{ $project->status }}</td>
                        <td>{{ $project->priority }}</td>
                        <td>{{ $project->risk }}</td>
                        <td>{{ $project->progress_percent }}%</td>
                        <td>{{ $scoring->projectHealth($project) }}</td>
                        <td>{{ $project->latestAssignment?->department?->name ?? 'Unassigned' }}</td>
                        <td>{{ $project->deadline->toDateString() }}</td>
                        <td class="text-right"><a class="link" href="{{ route('projects.show', $project) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No projects found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $projects->links() }}</div>
    </section>

    @if(auth()->user()->canManage())
        <section class="panel mt-6">
            <h2 class="section-title">Import Projects</h2>
            <form method="POST" action="{{ route('projects.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                @csrf
                <input class="input max-w-md" type="file" name="csv" accept=".csv,text/csv" required>
                <button class="btn-secondary" type="submit">Import CSV</button>
            </form>
        </section>
    @endif
@endsection
