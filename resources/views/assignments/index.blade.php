@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Assignment Center</h1>
        <p class="page-subtitle">Route projects and quotations to departments and notify assigned people by email.</p>
    </div>

    <section class="panel mt-6">
        <h2 class="section-title">Assign Work</h2>
        <form method="POST" action="{{ route('assignments.store') }}" class="mt-4 grid gap-4 lg:grid-cols-3">
            @csrf
            <label class="lg:col-span-2">
                <span class="label">Record</span>
                <select class="input" name="target" required>
                    <optgroup label="Projects">
                        @foreach($projects as $project)
                            <option value="project:{{ $project->id }}">
                                {{ $project->project_code }} - {{ $project->name }} ({{ $project->latestAssignment?->department?->name ?? 'Unassigned' }})
                            </option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Quotations">
                        @foreach($quotations as $quotation)
                            <option value="quotation:{{ $quotation->id }}">
                                {{ $quotation->quotation_code }} - {{ $quotation->opportunity }} ({{ $quotation->latestAssignment?->department?->name ?? 'Unassigned' }})
                            </option>
                        @endforeach
                    </optgroup>
                </select>
            </label>
            <label>
                <span class="label">Department</span>
                <select class="input" name="department_id" required>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Existing User</span>
                <select class="input" name="assigned_user_id">
                    <option value="">Manual assignee</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}{{ $user->department ? " · {$user->department->name}" : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Assignee Name</span>
                <input class="input" name="assignee_name" value="{{ old('assignee_name') }}">
            </label>
            <label>
                <span class="label">Assignee Email</span>
                <input class="input" type="email" name="assignee_email" value="{{ old('assignee_email') }}">
            </label>
            <label class="flex items-center gap-2 text-sm text-zinc-700">
                <input class="rounded border-zinc-300" type="checkbox" name="send_email" value="1" checked>
                Send assignment email
            </label>
            <div class="lg:col-span-3">
                <button class="btn-primary" type="submit">Assign</button>
            </div>
        </form>
    </section>

    <section class="panel mt-6 overflow-x-auto">
        <h2 class="section-title">Recent Assignments</h2>
        <table class="data-table mt-4">
            <thead><tr><th>Record</th><th>Department</th><th>Assignee</th><th>Status</th><th>Assigned</th></tr></thead>
            <tbody>
                @forelse($assignments as $assignment)
                    @php($record = $assignment->assignable)
                    <tr>
                        <td>{{ $record->project_code ?? $record->quotation_code }} - {{ $record->name ?? $record->opportunity }}</td>
                        <td>{{ $assignment->department->name }}</td>
                        <td>{{ $assignment->assignee_name }}<div class="text-xs text-zinc-500">{{ $assignment->assignee_email }}</div></td>
                        <td>{{ $assignment->status }}</td>
                        <td>{{ $assignment->assigned_at?->toDayDateTimeString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No assignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $assignments->links() }}</div>
    </section>

    <section class="panel mt-6">
        <h2 class="section-title">Assignment Email Log</h2>
        <div class="mt-4 space-y-3">
            @forelse($emailLogs as $log)
                <div class="rounded-md border border-zinc-200 p-3 text-sm">
                    <div class="font-medium">{{ $log->subject }} · {{ $log->status }}</div>
                    <div class="text-zinc-500">{{ $log->recipient }} · {{ $log->created_at->toDayDateTimeString() }}</div>
                    @if($log->message)<div class="mt-1 text-rose-700">{{ $log->message }}</div>@endif
                </div>
            @empty
                <p class="empty">No assignment email activity.</p>
            @endforelse
        </div>
    </section>
@endsection
