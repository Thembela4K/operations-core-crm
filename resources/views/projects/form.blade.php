@php($limited = $project->exists && ! auth()->user()->canManage())

@if($limited)
    <div class="grid gap-4 md:grid-cols-3">
        <label><span class="label">Status</span><select class="input" name="status">@foreach($statuses as $status)<option @selected(old('status', $project->status) === $status)>{{ $status }}</option>@endforeach</select></label>
        <label><span class="label">Progress %</span><input class="input" type="number" min="0" max="100" name="progress_percent" value="{{ old('progress_percent', $project->progress_percent) }}" required></label>
        <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-28" name="notes">{{ old('notes', $project->notes) }}</textarea></label>
    </div>
@else
    <div class="grid gap-4 md:grid-cols-3">
        <label><span class="label">Project ID</span><input class="input" name="project_code" value="{{ old('project_code', $project->project_code) }}" required></label>
        <label class="md:col-span-2"><span class="label">Project Name</span><input class="input" name="name" value="{{ old('name', $project->name) }}" required></label>
        <label><span class="label">Owner</span><input class="input" name="owner" value="{{ old('owner', $project->owner) }}" required></label>
        <label><span class="label">Owner Email</span><input class="input" type="email" name="owner_email" value="{{ old('owner_email', $project->owner_email) }}"></label>
        <label><span class="label">Status</span><select class="input" name="status">@foreach($statuses as $status)<option @selected(old('status', $project->status) === $status)>{{ $status }}</option>@endforeach</select></label>
        <label><span class="label">Priority</span><select class="input" name="priority">@foreach($priorities as $priority)<option @selected(old('priority', $project->priority) === $priority)>{{ $priority }}</option>@endforeach</select></label>
        <label><span class="label">Risk</span><select class="input" name="risk">@foreach($risks as $risk)<option @selected(old('risk', $project->risk) === $risk)>{{ $risk }}</option>@endforeach</select></label>
        <label><span class="label">Rating</span><input class="input" type="number" min="0" max="5" step="0.1" name="rating" value="{{ old('rating', $project->rating) }}" required></label>
        <label><span class="label">Progress %</span><input class="input" type="number" min="0" max="100" name="progress_percent" value="{{ old('progress_percent', $project->progress_percent) }}" required></label>
        <label><span class="label">Budget</span><input class="input" type="number" min="0" step="0.01" name="budget" value="{{ old('budget', $project->budget) }}" required></label>
        <label><span class="label">Start Date</span><input class="input" type="date" name="start_date" value="{{ old('start_date', $project->start_date ? \Illuminate\Support\Carbon::parse($project->start_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required></label>
        <label><span class="label">Deadline</span><input class="input" type="date" name="deadline" value="{{ old('deadline', $project->deadline ? \Illuminate\Support\Carbon::parse($project->deadline)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}" required></label>
        <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-28" name="notes">{{ old('notes', $project->notes) }}</textarea></label>
    </div>
@endif
